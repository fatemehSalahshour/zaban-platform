<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * همگام‌سازی سؤال‌ها از دیتابیس پلتفرم آزمون (azmoon).
 *
 * چرا این دستور به‌جای CSV:
 *   پلتفرم آزمون مدام به‌روز می‌شود. با CSV هر بار باید خروجی بگیرید،
 *   UTF-8 را درست کنید و وارد کنید. این دستور مستقیم می‌خواند، پس
 *   هر وقت لازم شد یک بار اجرا می‌شود و تمام.
 *
 * نکته‌ی اصلی — شماره‌ی سؤال در دفترچه وجود ندارد:
 *   در azmoon ستون `number` روی والدها صفر است و روی فرزندها شماره‌ی
 *   سؤال «داخل همان پسیج» را نگه می‌دارد، نه شماره‌ی دفترچه.
 *   پس شماره‌ی دفترچه بازسازی می‌شود: والدهای هر سال و رشته به ترتیب
 *   چیده می‌شوند و هر والد به تعداد سؤال‌هایش شماره می‌گیرد.
 *     وکب  → یک سؤال
 *     کلوز و پسیج → به تعداد childes_count
 *
 * ترتیب چیدن:
 *   `ORDER BY sort, id`. ستون sort در بعضی دفترچه‌ها پر است و در بعضی
 *   صفر (۱۴۰۴ کلاً صفر است، ۱۴۰۳ پر). وقتی صفر باشد id تصمیم می‌گیرد.
 *
 * تأیید:
 *   بازسازی حدس است، پس نتیجه با word_occurrences مقایسه می‌شود —
 *   جدولی که از اکسل کلمات ساخته شده و مستقل از اینجاست. هر دفترچه‌ای
 *   که نخواند گزارش می‌شود و به‌طور پیش‌فرض وارد نمی‌شود.
 *
 * استفاده:
 *   php artisan zaban:sync-questions --check          فقط گزارش
 *   php artisan zaban:sync-questions                  فقط دفترچه‌های تأییدشده
 *   php artisan zaban:sync-questions --force          حتی ناهمخوان‌ها
 *   php artisan zaban:sync-questions --year=1404
 */
class SyncQuestions extends Command
{
    protected $signature = 'zaban:sync-questions
        {--check   : فقط بررسی کن، چیزی ننویس}
        {--force   : دفترچه‌های ناهمخوان با بانک کلمات را هم وارد کن}
        {--year=   : فقط یک سال}
        {--exam=   : فقط یک رشته (ce|it|cs)}';

    protected $description = 'آوردن سؤال‌ها و متن‌ها از دیتابیس پلتفرم آزمون';

    /** major_id در azmoon به کد رشته‌ی این پلتفرم */
    private const MAJOR = [1 => 'ce', 2 => 'it', 3 => 'cs'];

    /** kind در azmoon به نام بخش */
    private const KIND = [1 => 'vocab', 2 => 'passage', 3 => 'cloze'];

    private array $report = [];

    public function handle(): int
    {
        $check = (bool) $this->option('check');

        try {
            DB::connection('azmoon')->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به دیتابیس azmoon برقرار نشد: ' . $e->getMessage());
            $this->line('اتصال «azmoon» را در config/database.php تعریف کرده‌اید؟');
            return 1;
        }

        $books = $this->books();
        if (!$books) {
            $this->error('هیچ دفترچه‌ای پیدا نشد.');
            return 1;
        }

        $this->info(count($books) . ' دفترچه پیدا شد.');
        $this->newLine();

        $ok = $bad = $wrote = 0;

        foreach ($books as $book) {
            $plan = $this->plan($book);
            if (!$plan || empty($plan['rows'])) {
                $this->line("  <fg=gray>-</> {$book->year} " . self::MAJOR[$book->major_id] . ' — خالی، رد شد');
                continue;
            }
            $verdict = $this->verify($book, $plan);
            $label   = $book->year . ' ' . self::MAJOR[$book->major_id];

            if ($verdict['ok']) {
                $ok++;
                $this->line("  <fg=green>✓</> $label — {$plan['total']} سؤال");
            } else {
                $bad++;
                $this->line("  <fg=yellow>!</> $label — " . $verdict['why']);
                $this->report[] = "$label: " . $verdict['why'];
            }

            if ($check) continue;
            if (!$verdict['ok'] && !$this->option('force')) continue;

            $this->write($book, $plan);
            $wrote++;
        }

        $this->newLine();
        $this->info("همخوان: $ok   ناهمخوان: $bad");

        if ($this->report) {
            $this->newLine();
            $this->warn('دفترچه‌های ناهمخوان:');
            foreach ($this->report as $r) $this->line('  ' . $r);
            $this->newLine();
            $this->line('اینها یعنی شماره‌های بازسازی‌شده با بانک کلمات نمی‌خوانند.');
            $this->line('یا در azmoon سؤالی جا افتاده، یا ترتیب والدها درست نیست.');
            $this->line('با --force می‌توانید وارد کنید، ولی اول علتش را ببینید.');
        }

        if ($check) {
            $this->newLine();
            $this->info('حالت بررسی — چیزی نوشته نشد.');
            return $bad ? 1 : 0;
        }

        $this->info("$wrote دفترچه وارد شد.");
        app(\App\Services\ContentBuilder::class)->bump();

        return 0;
    }

    /* ---------------- خواندن از azmoon ---------------- */

    /** فهرست دفترچه‌ها: هر ترکیب سال و رشته یک دفترچه است. */
    private function books(): array
    {
        $q = DB::connection('azmoon')->table('question')
            ->where('is_language', 1)
            ->where('parent_id', 0)
            ->where('type', 'sarasari')
            ->whereIn('major_id', array_keys(self::MAJOR))
            ->where('status', '!=', 'deleted')
            ->selectRaw('year, major_id, COUNT(*) AS parents')
            ->groupBy('year', 'major_id')
            ->orderByDesc('year')->orderBy('major_id');

        if ($y = $this->option('year')) $q->where('year', $y);
        if ($e = $this->option('exam')) {
            $id = array_search($e, self::MAJOR, true);
            if ($id === false) { $this->error("رشته‌ی نامعتبر: $e"); return []; }
            $q->where('major_id', $id);
        }

        return $q->get()->all();
    }

    /**
     * بازسازی نقشه‌ی دفترچه.
     * خروجی: بخش‌ها، متن‌ها، و سؤال‌ها با شماره‌ی دفترچه.
     */
    private function plan(object $book): ?array
    {
        $parents = DB::connection('azmoon')->table('question')
            ->where('is_language', 1)->where('parent_id', 0)
            ->where('type', 'sarasari')
            ->where('year', $book->year)->where('major_id', $book->major_id)
            ->where('status', '!=', 'deleted')
            ->orderBy('sort')->orderBy('id')   /* sort در بعضی سال‌ها صفر است */
            ->get();

        if ($parents->isEmpty()) return null;

        $sections = [];
        $texts    = [];
        $rows     = [];
        $n        = 0;            /* شماره‌ی جاری در دفترچه */
        $passNo   = 0;            /* شمارنده‌ی پسیج */
        $order    = 0;

        foreach ($parents as $p) {
            $sec = self::KIND[$p->kind] ?? null;
            if (!$sec) continue;

            if ($sec === 'vocab') {
                /* وکب: خود والد یک سؤال است */
                $n++;
                $rows[] = $this->leaf($p, $n, 'vocab', null);
                $sections[] = ['vocab', null, $n, $n, ++$order];
                continue;
            }

            /* کلوز یا پسیج: والد متن است، فرزندها سؤال */
            $kids = DB::connection('azmoon')->table('question')
                ->where('parent_id', $p->id)
                ->where('status', '!=', 'deleted')
                ->orderBy('number')->orderBy('id')
                ->get();

            if ($kids->isEmpty()) continue;

            $pno   = $sec === 'passage' ? ++$passNo : null;
            $from  = $n + 1;

            foreach ($kids as $k) {
                $n++;
                $rows[] = $this->leaf($k, $n, $sec, $pno);
            }

            $sections[] = [$sec, $pno, $from, $n, ++$order];
            $texts[]    = [
                'section'        => $sec,
                'passage_number' => $pno,
                'body'           => $this->clean($p->title),
                'body_fa'        => $this->clean($p->translate) ?: null,
            ];
        }

        return $rows ? compact('sections', 'texts', 'rows') + ['total' => $n] : null;
    }

    /** یک سؤال با گزینه‌هایش. */
    private function leaf(object $q, int $number, string $sec, ?int $pno): array
    {
        $opts = DB::connection('azmoon')->table('answer')
            ->where('question_id', $q->id)
            ->orderBy('id')            /* ترتیب گزینه‌ها با id است */
            ->get(['title', 'is_correct']);

        $options = [];
        $correct = null;
        foreach ($opts as $i => $o) {
            $options[] = $this->clean($o->title);
            if ($o->is_correct === 'yes' && $correct === null) $correct = $i + 1;
        }

        return [
            'number'   => $number,
            'section'  => $sec,
            'passage'  => $pno,
            'stem'     => $this->clean($q->title),
            'options'  => $options,
            'correct'  => $correct,
            'explain'  => $this->clean($q->answer) ?: null,
            'src_id'   => $q->id,
            'view' => in_array((int) $q->view, [3,6,12], true) ? (int) $q->view : 3,
        ];
    }

    /* ---------------- تأیید با بانک کلمات ---------------- */

    /**
     * نقشه‌ی بازسازی‌شده را با word_occurrences مقایسه می‌کند.
     * آن جدول از اکسل کلمات آمده و کاملاً مستقل از azmoon است،
     * پس اگر هر دو یک چیز بگویند، بازسازی درست بوده.
     */
    private function verify(object $book, array $plan): array
    {
        $exam = self::MAJOR[$book->major_id];

        if (count($plan['rows']) !== $plan['total']) {
            return ['ok' => false, 'why' => 'شمارش داخلی نمی‌خواند'];
        }

        $known = DB::table('word_occurrences')
            ->where('year', (int) $book->year)->where('exam', $exam)
            ->whereNotNull('test_number')->where('test_number', '>', 0)
            ->selectRaw('section, MIN(test_number) AS lo, MAX(test_number) AS hi')
            ->groupBy('section')->get()->keyBy('section');

        if ($known->isEmpty()) {
            return ['ok' => true, 'why' => ''];   /* چیزی برای مقایسه نیست */
        }

        /* محدوده‌ی هر بخش طبق بازسازی */
        $mine = [];
        foreach ($plan['sections'] as [$sec, $pno, $from, $to, $ord]) {
            $mine[$sec]['lo'] = min($mine[$sec]['lo'] ?? 999, $from);
            $mine[$sec]['hi'] = max($mine[$sec]['hi'] ?? 0,   $to);
        }

        foreach ($known as $sec => $k) {
            if (!isset($mine[$sec])) {
                return ['ok' => false, 'why' => "بخش «{$sec}» در azmoon نیست ولی در بانک کلمات هست"]; 
            }
            /* بانک کلمات ممکن است همه‌ی سؤال‌ها را پوشش ندهد، پس فقط
               بررسی می‌کنیم که داخل محدوده‌ی بازسازی جا بگیرد. */
            if ($k->lo < $mine[$sec]['lo'] || $k->hi > $mine[$sec]['hi']) {
                return ['ok' => false, 'why' => sprintf(
                    '%s: بانک کلمات %d–%d می‌گوید، بازسازی %d–%d',
                    $sec, $k->lo, $k->hi, $mine[$sec]['lo'], $mine[$sec]['hi']
                )];
            }
        }

        return ['ok' => true, 'why' => ''];
    }

    /* ---------------- نوشتن ---------------- */

    private function write(object $book, array $plan): void
    {
        $year = (int) $book->year;
        $exam = self::MAJOR[$book->major_id];

        DB::transaction(function () use ($year, $exam, $plan) {

            /* بخش‌ها */
            DB::table('exam_sections')->where('year', $year)->where('exam', $exam)->delete();
            foreach ($plan['sections'] as [$sec, $pno, $from, $to, $ord]) {
                DB::table('exam_sections')->insert([
                    'year' => $year, 'exam' => $exam, 'section' => $sec,
                    'passage_number' => $pno,
                    'from_question' => $from, 'to_question' => $to,
                    'sort_order' => $ord,
                ]);
            }

            /* متن‌ها */
            foreach ($plan['texts'] as $t) {
                DB::table('exam_texts')->updateOrInsert(
                    ['year' => $year, 'exam' => $exam,
                     'section' => $t['section'], 'passage_number' => $t['passage_number']],
                    ['body' => $t['body'], 'body_fa' => $t['body_fa'], 'updated_at' => now()]
                );
            }

            /* سؤال‌ها */
            foreach ($plan['rows'] as $r) {
                DB::table('questions')->updateOrInsert(
                    ['year' => $year, 'exam' => $exam, 'question_number' => $r['number']],
                    ['section' => $r['section'], 'passage_number' => $r['passage'],
                     'stem' => $r['stem'], 'explanation' => $r['explain'],'opt_view' => $r['view'],
                     'correct_option' => $r['correct'] ?: 1, 'updated_at' => now()]
                );

                $qid = DB::table('questions')->where('year', $year)->where('exam', $exam)
                    ->where('question_number', $r['number'])->value('id');

                foreach ($r['options'] as $i => $body) {
                    DB::table('question_options')->updateOrInsert(
                        ['question_id' => $qid, 'position' => $i + 1],
                        ['body' => mb_substr($body, 0, 190),
                         'is_correct' => ($i + 1) === $r['correct'] ? 1 : 0]
                    );
                }
            }
        });
    }

    /* ---------------- ابزار ---------------- */

    /**
     * محتوای azmoon همه HTML است: <div dir="ltr">، &hellip;، &zwnj;، &nbsp;.
     * اینجا به متن ساده تبدیل می‌شود. نیم‌فاصله حفظ می‌شود چون در فارسی
     * معنادار است، ولی فاصله‌ی مجازی HTML به فاصله‌ی معمولی تبدیل می‌شود.
     */
    private function clean(?string $html): string
    {
        if ($html === null || $html === '') return '';

        $s = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html);
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $s);  /* nbsp و zero-width */
        $s = preg_replace('/[ \t]+/u', ' ', $s);
        $s = preg_replace('/\n{3,}/u', "\n\n", $s);

        return trim($s);
    }
}
