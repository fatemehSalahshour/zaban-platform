<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * وارد کردن دیتای واقعی از اکسل‌های استخراج‌شده.
 *
 *   php artisan zaban:import sections storage/app/zaban/exam_sections.csv
 *   php artisan zaban:import texts    storage/app/zaban/texts.csv
 *   php artisan zaban:import words    storage/app/zaban/words.csv
 *   php artisan zaban:import questions storage/app/zaban/questions.csv
 *   php artisan zaban:import finalize
 *
 * ترتیب مهم است: کلمات قبل از سؤال‌ها، تا گزینه‌ها به کلمات وصل شوند.
 * دستور idempotent است — اجرای دوباره ردیف تکراری نمی‌سازد.
 */
class ImportZaban extends Command
{
    protected $signature = 'zaban:import {what : sections|texts|words|questions|finalize} {file?} {--check : فقط بررسی کن، چیزی ننویس}';
    protected $description = 'وارد کردن دیتای پلتفرم زبان از CSV';

    private const EXAM = ['CE' => 'ce', 'IT' => 'it', 'CS' => 'cs',
                          'ce' => 'ce', 'it' => 'it', 'cs' => 'cs'];
    private const SEC  = ['Vocab' => 'vocab', 'Cloze' => 'cloze', 'Passage' => 'passage',
                          'vocab' => 'vocab', 'cloze' => 'cloze', 'passage' => 'passage'];
    /** خطاهای جمع‌شده در حالت --check */
    private array $problems = [];
    public function handle(): int
    {
        $what = $this->argument('what');
        if ($what === 'finalize') return $this->finalize();

        $file = $this->argument('file');
        if (!$file || !is_readable($file)) { $this->error("فایل خوانده نشد: $file"); return 1; }

        return match ($what) {
            'sections'  => $this->rows($file, fn ($r) => $this->section($r)),
            'texts'     => $this->rows($file, fn ($r) => $this->text($r)),
            'words'     => $this->rows($file, fn ($r) => $this->word($r)),
            'questions' => $this->rows($file, fn ($r) => $this->questionRow($r)),
            default     => tap(1, fn () => $this->error('نوع نامعتبر')),
        };
    }

    private function rows(string $file, callable $fn): int
    {
        $check = (bool) $this->option('check');
        $fh    = fopen($file, 'r');
        $head  = array_map(fn ($h) => trim($h, "\xEF\xBB\xBF \t"), fgetcsv($fh));
        $n = 0; $line_no = 1;

        DB::beginTransaction();
        try {
            while (($line = fgetcsv($fh)) !== false) {
                $line_no++;
                if (count($line) === 1 && trim((string) $line[0]) === '') continue;
                $row = array_combine($head, array_pad($line, count($head), null));

                if ($check) {
                    /* در حالت بررسی، خطای هر ردیف را نگه می‌داریم و می‌رویم بعدی */
                    try { $fn($row); }
                    catch (\Throwable $e) {
                        $this->problems[] = "خط $line_no: " . $e->getMessage();
                    }
                } else {
                    $fn($row);
                }

                if (++$n % 500 === 0) $this->output->write('.');
            }

            /* در حالت بررسی هیچ‌چیز نوشته نمی‌شود */
            if ($check) DB::rollBack(); else DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("\nخط $line_no: " . $e->getMessage());
            return 1;
        }
        fclose($fh);

        if ($check) {
            if (!$this->problems) {
                $this->info("\n✓ $n ردیف بررسی شد، مشکلی نبود. حالا بدون --check اجرا کنید.");
                return 0;
            }
            $this->error("\n" . count($this->problems) . " مشکل در $n ردیف:");
            foreach ($this->problems as $p) $this->line('  ' . $p);
            return 1;
        }

        $this->info("\n$n ردیف وارد شد.");
        return 0;
    }

    /* ---------- exam_sections.csv ---------- */
    private function section(array $r): void
    {
        DB::table('exam_sections')->updateOrInsert(
            ['year' => (int) $r['year'], 'exam' => self::EXAM[$r['exam']],
             'section' => self::SEC[$r['section']],
             'passage_number' => $r['passage_number'] !== '' ? (int) $r['passage_number'] : null],
            ['from_question' => (int) $r['from_question'], 'to_question' => (int) $r['to_question'],
             'sort_order' => (int) ($r['sort_order'] ?? 0)]
        );
    }

    /* ---------- texts.csv ---------- */
    private function text(array $r): void
    {
        DB::table('exam_texts')->updateOrInsert(
            ['year' => (int) $r['year'], 'exam' => self::EXAM[$r['exam']],
             'section' => self::SEC[$r['section']],
             'passage_number' => $r['passage_number'] !== '' ? (int) $r['passage_number'] : null],
            ['title' => $r['title'] ?: null, 'body' => $r['body'],
             'body_fa' => $r['body_fa'] ?: null, 'updated_at' => now()]
        );
    }
    /** اولین ستون موجود از میان چند نام ممکن. «-» و «null» را خالی می‌شمارد. */
    private function col(array $r, array $names, string $default = ''): string
    {
        foreach ($names as $n) {
            if (!array_key_exists($n, $r)) continue;
            $v = trim((string) $r[$n]);
            if ($v !== '' && $v !== '-' && strtolower($v) !== 'null') return $v;
        }
        return $default;
    }

    /**
     * نقش دستوری پایه. pos_raw دست‌نخورده می‌ماند و همان چیزی است که به
     * کاربر نشان داده می‌شود؛ این فقط برای گروه‌بندی و ساختن گزینه‌های غلط است.
     * ترتیب مهم است: adverb قبل از verb، چون رشته‌ی «adverb» خودش «verb» را
     * در خود دارد و اگر جابه‌جا باشند همه‌ی قیدها فعل ثبت می‌شوند.
     */
    private function basePos(string $raw): ?string
    {
        $first = strtolower(trim(explode('/', $raw)[0]));
        foreach ([
            'phrasal'     => 'Phrasal Verb',
            'adverb'      => 'Adverb',
            'adjective'   => 'Adjective',
            'noun'        => 'Noun',
            'infinitive'  => 'Verb',
            'verb'        => 'Verb',
            'preposition' => 'Preposition',
            'conjunction' => 'Conjunction',
            'pronoun'     => 'Pronoun',
            'clause'      => 'Clause',
        ] as $needle => $label) {
            if (str_contains($first, $needle)) return $label;
        }
        return null;
    }

    private function word(array $r): void
    {
        $word = $this->col($r, ['Word']);
        if ($word === '') return;

        $posRaw = $this->col($r, ['Type']);

        $wid  = DB::table('words')->where('word', $word)->value('id');
        /* معنی‌ها ادغام می‌شوند، نه بازنویسی. یک کلمه می‌تواند در کلوز یک نقش
        داشته باشد و در پسیج نقش دیگر — مثل that که هم موصولی است هم اشاره.
        بازنویسی یعنی هر بار فقط آخرین معنی بماند. */
        $newFa = $this->col($r, ['Persian Meaning', 'Meaning']);
        $oldFa = $wid ? (string) DB::table('words')->where('id', $wid)->value('meaning_fa') : '';

        $parts = array_filter(array_map('trim', explode('/', $oldFa)));
        $fresh = array_filter(array_map('trim', explode('/', $newFa)));
        foreach ($fresh as $f) {
            if ($f !== '' && !in_array($f, $parts, true)) $parts[] = $f;
        } 

        $data = [
            'meaning_fa'  => implode(' / ', $parts),
            'pos_raw'     => $posRaw ?: null,
            'pos_primary' => $this->basePos($posRaw),
            'is_phrase'   => preg_match('/phrase|phrasal|idiom/i', $posRaw) ? 1 : 0,
            'level'       => $this->col($r, ['Level'], 'متوسط'),
            'updated_at'  => now(),
        ];

        $forms = array_map('trim', explode('/', $this->col($r, ['Verb Forms'])));
        foreach (['form_base' => 0, 'form_past' => 1, 'form_participle' => 2] as $c => $i) {
            $v = $forms[$i] ?? null; 
            $data[$c] = ($v === null || $v === '' || strtolower($v) === 'null') ? null : $v;
        }

        if ($wid) DB::table('words')->where('id', $wid)->update($data);
        else      $wid = DB::table('words')->insertGetId($data + ['word' => $word, 'created_at' => now()]);

        /* ظهور. اگر Test# خالی یا «متن» باشد، یعنی کلمه داخل متن آمده نه در سؤال. */
        $testRaw = $this->col($r, ['Test#']);
        
        /* «متن» در دو شیت، و «passage»/«cloze» در شیت دیگر — همه یعنی کلمه داخل
        متن آمده نه در سؤال. هر چیزی که عدد نیست، متن حساب می‌شود. */
        $isText = ($testRaw === '' || !ctype_digit($testRaw));

        $test    = $isText ? null : (int) $testRaw;

        $passRaw = $this->col($r, ['Passage#']);
        $pass    = $passRaw !== '' ? (int) $passRaw : null;

        $secRaw = $this->col($r, ['Section']);
        $sec    = self::SEC[$secRaw] ?? null;
        if (!$sec) throw new \RuntimeException("بخش نامعتبر «{$secRaw}» برای کلمه‌ی «{$word}»");
        
        /* بعضی سال‌ها دفترچه‌ی آی‌تی و علوم کامپیوتر مشترک است و در اکسل به شکل
        «IT / CS» نوشته شده. یک ردیف اکسل در آن حالت دو ظهور واقعی است، پس
        برای هر رشته جداگانه ثبت می‌شود — وگرنه آمار یکی از دو رشته ناقص می‌ماند. */
        $examRaw  = $this->col($r, ['Exam']);
        $examList = [];
        foreach (preg_split('/[\/،,]+/', $examRaw) as $e) {
            $e = trim($e);
            if ($e === '') continue;
            $code = self::EXAM[$e] ?? null;
            if (!$code) throw new \RuntimeException("رشته‌ی نامعتبر «{$e}» برای کلمه‌ی «{$word}»");
            $examList[$code] = true;
        }
        if (!$examList) throw new \RuntimeException("رشته خالی است برای کلمه‌ی «{$word}»");

        $slot = $sec . ($pass ?: '') . ':' . ($test ?? 'text');
        $year = (int) $this->col($r, ['Year']);

        foreach (array_keys($examList) as $exam) {
            DB::table('word_occurrences')->updateOrInsert(
                ['word_id' => $wid, 'year' => $year, 'exam' => $exam, 'slot' => $slot],
                ['section' => $sec, 'test_number' => $test, 'passage_number' => $pass,
                'source' => $isText ? 'text' : 'question']
            );
        }

        for ($i = 1; $i <= 2; $i++) {
            $s = $this->col($r, ["Example $i", "Example{$i}"]);
            if ($s === '') continue;
            DB::table('word_examples')->updateOrInsert(
                ['word_id' => $wid, 'sentence_hash' => md5($s)],
                ['sentence' => $s,
                'sentence_fa' => $this->col($r, ["Example $i FA", "Example{$i} Meaning", "Example $i Meaning"]) ?: null]
            );
        }
    }


    /* ---------- questions.csv ---------- */
    private function questionRow(array $r): void
    {
        $key = ['year' => (int) $r['year'], 'exam' => self::EXAM[$r['exam']],
                'question_number' => (int) $r['question_number']];

        DB::table('questions')->updateOrInsert($key, [
            'section' => self::SEC[$r['section']],
            'passage_number' => $r['passage_number'] !== '' ? (int) $r['passage_number'] : null,
            'stem' => $r['stem'], 'stem_fa' => $r['stem_fa'] ?: null,
            'explanation' => $r['explanation'] ?: null,
            'correct_option' => (int) $r['correct_option'],
            'updated_at' => now(),
        ]);
        $qid = DB::table('questions')->where($key)->value('id');

        for ($i = 1; $i <= 4; $i++) {
            DB::table('question_options')->updateOrInsert(
                ['question_id' => $qid, 'position' => $i],
                ['body' => trim((string) $r["option_$i"]),
                 'is_correct' => ((int) $r['correct_option'] === $i) ? 1 : 0]
            );
        }
    }

    /* ---------- finalize ---------- */
    private function finalize(): int
    {
        $this->line('۱) شمارنده‌های کلمات…');
        DB::statement("
            UPDATE words w
            LEFT JOIN (SELECT word_id, COUNT(DISTINCT year) y, COUNT(*) o,
                              MAX(year) ly, MIN(year) fy
                       FROM word_occurrences GROUP BY word_id) x ON x.word_id = w.id
            SET w.year_count = COALESCE(x.y,0), w.occurrence_count = COALESCE(x.o,0),
                w.last_year = x.ly, w.first_year = x.fy");

        $this->line('۲) وصل کردن متن به سؤال‌های کلوز و پسیج…');
        DB::statement("
            UPDATE questions q JOIN exam_texts t
              ON t.year = q.year AND t.exam = q.exam AND t.section = q.section
             AND (t.passage_number <=> q.passage_number)
            SET q.text_id = t.id WHERE q.section IN ('cloze','passage')");

        $this->line('۳) وصل کردن ظهورها به سؤال‌ها…');
        DB::statement("
            UPDATE word_occurrences o JOIN questions q
              ON q.year = o.year AND q.exam = o.exam AND q.question_number = o.test_number
            SET o.question_id = q.id WHERE o.source = 'question'");

        $this->line('۴) وصل کردن گزینه‌ها به کلمات بانک…');
        DB::statement("UPDATE question_options o JOIN words w ON w.word = o.body SET o.word_id = w.id");

        $this->line('۵) ساخت جدول کلمات هر سؤال…');
        DB::statement("
            INSERT IGNORE INTO question_words (question_id, word_id, role)
            SELECT o.question_id, o.word_id,
                   CASE WHEN o.is_correct = 1 THEN 'answer' ELSE 'option' END
            FROM question_options o WHERE o.word_id IS NOT NULL");
        DB::statement("
            INSERT IGNORE INTO question_words (question_id, word_id, role)
            SELECT oc.question_id, oc.word_id, 'stem'
            FROM word_occurrences oc WHERE oc.question_id IS NOT NULL");

        $this->line('۶) بررسی‌های سلامت:');
        $this->checks();
        $this->info('تمام شد. حالا: php artisan zaban:predict && php artisan cache:clear');
        return 0;
    }

    private function checks(): void
    {
        $q = [
            'کلمات بدون هیچ ظهور' =>
                'SELECT COUNT(*) c FROM words w LEFT JOIN word_occurrences o ON o.word_id=w.id WHERE o.id IS NULL',
            'سؤال‌های بدون چهار گزینه' =>
                'SELECT COUNT(*) c FROM (SELECT question_id FROM question_options GROUP BY question_id HAVING COUNT(*)<>4) t',
            'سؤال کلوز/پسیج بدون متن' =>
                "SELECT COUNT(*) c FROM questions WHERE section IN ('cloze','passage') AND text_id IS NULL",
            'شماره سؤال بیرون از exam_sections' =>
                'SELECT COUNT(*) c FROM questions q LEFT JOIN exam_sections s
                   ON s.year=q.year AND s.exam=q.exam
                  AND q.question_number BETWEEN s.from_question AND s.to_question
                 WHERE s.id IS NULL',
        ];
        foreach ($q as $label => $sql) {
            $c = (int) DB::selectOne($sql)->c;
            $this->line(sprintf('   %-38s %s', $label, $c === 0 ? '✓ صفر' : "✗ $c مورد"));
        }
    }
}
