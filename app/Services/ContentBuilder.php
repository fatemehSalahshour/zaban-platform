<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ساخت و کش کردن محتوای هر رشته.
 *
 * دو تغییر مهم نسبت به نسخه‌ی اول:
 *
 *  ۱) payload دو تکه شد. «هسته» (سال‌ها، ساختار، کلمات، متن‌ها) چیزی است که
 *     صفحه‌ی اول لازم دارد؛ «سؤال‌ها» فقط وقتی لازم است که کاربر سراغ تب آزمون
 *     برود. با ۲۵ سال و سه رشته، سؤال‌ها بزرگ‌ترین بخش payload بودند و
 *     تا وقتی همه‌چیز یک‌جا بود، اولین بارگذاری بی‌دلیل کند می‌شد.
 *
 *  ۲) نسخه‌ی محتوا در zaban_meta می‌نشیند نه در کش. cache:clear نباید
 *     ETag را عوض کند، وگرنه هر بار دیپلوی، مرورگر همه‌ی کاربرها دوباره
 *     یک مگابایت می‌گیرد.
 */
class ContentBuilder
{
    /**
     * نگاشت کد به نام نمایشی.
     *
     * پروتوتایپ همه‌جا با نام فارسی کار می‌کند (EXAM_NAMES / SEC_NAMES) و
     * deck و star را با «متن کلمه» کلید می‌زند نه با id. سرور با کد کار می‌کند.
     * ترجمه اینجا انجام می‌شود تا فقط یک نقطه‌ی تبدیل داشته باشیم.
     */
    public const EXAM_FA = ['ce' => 'مهندسی کامپیوتر', 'it' => 'آی‌تی', 'cs' => 'علوم کامپیوتر'];
    public const SEC_FA  = ['vocab' => 'وکب', 'cloze' => 'کلوز تست', 'passage' => 'پسیج'];

    /** کلید پاسخ هرگز در این خروجی نیست — تست خودکارش پایین همین کلاس است. */
    private const FORBIDDEN_KEYS = ['correct', 'correct_option', 'is_correct', 'explanation', 'ans'];

    public function version(string $exam): string
    {
        return (string) (DB::table('zaban_meta')->where('k', "content_version_$exam")->value('v') ?? '0');
    }

    /** بعد از import و predict صدا زده می‌شود. ETag عوض می‌شود، مرورگرها تازه می‌گیرند. */
    public function bump(?string $exam = null): void
    {
        $exams = $exam ? [$exam] : Entitlements::EXAMS;
        foreach ($exams as $e) {
            DB::table('zaban_meta')->updateOrInsert(
                ['k' => "content_version_$e"],
                ['v' => (string) time(), 'updated_at' => now()]
            );
            Cache::forget("zaban.content.core.$e");
            Cache::forget("zaban.content.questions.$e");
        }
    }

    public function etag(string $exam, string $part): string
    {
        return '"' . substr(sha1("$exam|$part|" . $this->version($exam)), 0, 20) . '"';
    }

    /* ================= هسته ================= */

    /**
     * @param int|null $year  نسخه‌ی نمایشی: فقط محتوای همین سال. null = کامل.
     *   محتوای سال‌های دیگر اصلاً از سرور بیرون نمی‌رود (نه اینکه در رابط پنهان شود).
     *   کلید کش نسخه‌ی نمایشی شامل نسخه‌ی محتوا است تا bump خودکار باطلش کند.
     */
    public function core(string $exam, ?int $year = null): array
    {
        $key = $year ? "zaban.content.core.$exam.y$year." . $this->version($exam) : "zaban.content.core.$exam";
        return Cache::remember($key, 86400, function () use ($exam, $year) {
            return [
                'version'   => $this->version($exam),
                'exam'      => $exam,
                'demo_year' => $year,
                'years'     => $this->years($exam, $year),
                'struct'    => $this->struct($exam, $year),
                'words'     => $this->words($exam, $year),
                'texts'     => $this->texts($exam, $year),
            ];
        });
    }

    private function years(string $exam, ?int $year = null): array
    {
        return DB::table('exam_sections')->where('exam', $exam)
            ->when($year, fn ($q) => $q->where('year', $year))
            ->distinct()->orderBy('year')->pluck('year')
            ->map(fn ($y) => (int) $y)->all();
    }

    private function struct(string $exam, ?int $year = null): array
    {
        $out = [];
        $rows = DB::table('exam_sections')->where('exam', $exam)
            ->when($year, fn ($q) => $q->where('year', $year))
            ->orderBy('year')->orderBy('sort_order')->get();

        foreach ($rows as $r) {
            $sec   = self::SEC_FA[$r->section] ?? $r->section;
            $label = $r->passage_number !== null
                ? $sec . ' ' . $this->faDigits((int) $r->passage_number)
                : $sec;
            $out[(string) $r->year][] = [
                $sec, $label,
                $r->passage_number !== null ? (int) $r->passage_number : null,
                (int) $r->from_question, (int) $r->to_question,
            ];
        }
        return $out;
    }

    /**
     * شکل خروجی عمداً همان چیزی است که پروتوتایپ بعد از decode() می‌سازد:
     *   {w, fa, pos, lvl(نام فارسی), forms, ipa, ex:[[en,fa]],
     *    occ:[[سال, نام فارسی رشته, نام فارسی بخش, شماره تست, شماره پسیج]]}
     * به‌اضافه‌ی id، که پروتوتایپ نداشت و برای مسیرهای سرور لازم است.
     *
     * اگر این شکل را عوض کنید، decode() در پروتوتایپ می‌شکند.
     */
    private function words(string $exam, ?int $year = null): array
    {
        $ids = DB::table('word_occurrences')->where('exam', $exam)
            ->when($year, fn ($q) => $q->where('year', $year))
            ->distinct()->pluck('word_id')->all();

        if (!$ids) return [];

        $words = DB::table('words')->whereIn('id', $ids)
            ->select('id', 'word', 'pos_primary', 'level',
                     'form_base', 'form_past', 'form_participle', 'pred_score')
            ->orderBy('word')->get();

        $occ = [];
        DB::table('word_occurrences')->whereIn('word_id', $ids)->where('exam', $exam)
            ->when($year, fn ($q) => $q->where('year', $year))     /* دمو: کاربرد در سال‌های دیگر هم لو نرود */
            ->orderBy('year', 'desc')
            ->get(['word_id', 'year', 'exam', 'section', 'test_number', 'passage_number'])
            ->each(function ($o) use (&$occ) {
                $occ[$o->word_id][] = [
                    (int) $o->year,
                    self::EXAM_FA[$o->exam] ?? $o->exam,
                    self::SEC_FA[$o->section] ?? $o->section,
                    (int) ($o->test_number ?? 0),
                    (int) ($o->passage_number ?? 0),
                ];
            });

        /* معنی و مثال عمداً اینجا نیستند (امنیت محتوا، قدم ۱ و ۳) —
           meanings()/examples() و /api/words/meanings، /api/words/detail */
        $out = [];
        foreach ($words as $w) {
            $forms = array_filter([$w->form_base, $w->form_past, $w->form_participle]);
            $out[] = [
                'id'    => (int) $w->id,
                'w'     => $w->word,
                'pos'   => $w->pos_primary,
                'lvl'   => $w->level,
                'forms' => $forms ? [$w->form_base, $w->form_past, $w->form_participle] : null,
                'ipa'   => null,
                'occ'   => $occ[$w->id] ?? [],
                'pred'  => $w->pred_score !== null ? (float) $w->pred_score : null,
            ];
        }
        return $out;
    }

    private function texts(string $exam, ?int $year = null): array
    {
        $out = [];
        DB::table('exam_texts')->where('exam', $exam)
            ->when($year, fn ($q) => $q->where('year', $year))
            ->get(['year', 'exam', 'section', 'passage_number', 'body', 'body_fa'])
            ->each(function ($t) use (&$out) {
                $key = $t->year . '|' . (self::EXAM_FA[$t->exam] ?? $t->exam)
                     . '|' . (self::SEC_FA[$t->section] ?? $t->section)
                     . '|' . ($t->passage_number ?? 0);
                $out[$key] = ['en' => $t->body, 'fa' => $t->body_fa];
            });
        return $out;
    }

    /* ================= سؤال‌ها ================= */

    public function questions(string $exam, ?int $year = null): array
    {
        $key = $year ? "zaban.content.questions.$exam.y$year." . $this->version($exam) : "zaban.content.questions.$exam";
        return Cache::remember($key, 86400, function () use ($exam, $year) {
            $qs = DB::table('questions')->where('exam', $exam)
                ->when($year, fn ($q) => $q->where('year', $year))
                ->orderBy('year', 'desc')->orderBy('question_number')
                ->get(['id', 'year', 'exam', 'question_number', 'section','opt_view',
                       'passage_number', 'text_id', 'stem', 'stem_fa']);

            if ($qs->isEmpty()) return ['version' => $this->version($exam), 'questions' => []];

            $qids = $qs->pluck('id')->all();

            $opts = [];
            DB::table('question_options')->whereIn('question_id', $qids)
                ->orderBy('question_id')->orderBy('position')
                // ← is_correct عمداً select نمی‌شود. اگر روزی کسی '*' بگذارد،
                //   assertNoAnswerKey پایین همین فایل جلویش را می‌گیرد.
                ->get(['question_id', 'position', 'body', 'word_id'])
                ->each(function ($o) use (&$opts) {
                    $opts[$o->question_id][(int) $o->position] = [$o->body, $o->word_id ? (int) $o->word_id : null];
                });

            $qw = [];
            DB::table('question_words')->whereIn('question_id', $qids)
                ->get(['question_id', 'word_id', 'role'])
                ->each(function ($r) use (&$qw) {
                    // نقش «answer» را به «option» تبدیل می‌کنیم؛ وگرنه از روی نقش،
                    // گزینه‌ی درست لو می‌رود بدون اینکه اسمش correct باشد.
                    $role = $r->role === 'answer' ? 'option' : $r->role;
                    $qw[$r->question_id][$role][] = (int) $r->word_id;
                });

            $out = [];
            foreach ($qs as $q) {
                $o = $opts[$q->id] ?? [];
                ksort($o);
                $out[] = [
                    'id' => (int) $q->id, 'y' => (int) $q->year,
                    'e' => self::EXAM_FA[$q->exam] ?? $q->exam,
                    'q' => (int) $q->question_number,
                    'sec' => self::SEC_FA[$q->section] ?? $q->section,
                    'p' => $q->passage_number !== null ? (int) $q->passage_number : 0,
                    'text_id' => $q->text_id ? (int) $q->text_id : null,
                    'stem' => $q->stem, 'stemFa' => $q->stem_fa,
                    'opts' => array_values(array_map(fn ($x) => $x[0], $o)),
                    'optWords' => array_values(array_map(fn ($x) => $x[1], $o)),
                    'words' => [
                        'option'  => array_values(array_unique($qw[$q->id]['option'] ?? [])),
                        'stem'    => array_values(array_unique($qw[$q->id]['stem'] ?? [])),
                        'passage' => array_values(array_unique($qw[$q->id]['passage'] ?? [])),
                    ],
                    'view' => (int) $q->opt_view,
                ];
            }
            return ['version' => $this->version($exam), 'questions' => $out];
        });
    }

    private function faDigits(int $n): string
    {
        return strtr((string) $n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴',
                                   '5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }

    /* ================= شبکه‌ی ایمنی ================= */

    /**
     * هر خروجی محتوا قبل از رفتن روی سیم از این رد می‌شود.
     * ارزان است (یک بار روی آرایه‌ی کش‌شده) و جلوی گران‌ترین اشتباه ممکن
     * در این پروژه را می‌گیرد: بیرون رفتن پاسخ‌نامه‌ی ۲۵ سال.
     */
    /**
     * مثال‌های چند کلمه — فقط برای /api/words/detail، پشت دسترسی و سقف روزانه.
     * @return array<int, array<int, array{0:string,1:string}>>  word_id ← [[جمله, ترجمه], …]
     */
    /** معنی چند کلمه — فقط برای /api/words/meanings، پشت دسترسی و سقف روزانه. */
    public function meanings(array $wordIds): array
    {
        if (!$wordIds) return [];
        return DB::table('words')->whereIn('id', $wordIds)->pluck('meaning_fa', 'id')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
    }

    public function examples(array $wordIds): array
    {
        $ex = [];
        if (!$wordIds) return $ex;
        DB::table('word_examples')->whereIn('word_id', $wordIds)->orderBy('id')
            ->get(['word_id', 'sentence', 'sentence_fa'])
            ->each(function ($e) use (&$ex) {
                $ex[(int) $e->word_id][] = [$e->sentence, $e->sentence_fa ?: ''];
            });
        return $ex;
    }

    public function assertNoAnswerKey(array $payload): void
    {
        $walk = function ($node) use (&$walk) {
            if (is_array($node)) {
                foreach ($node as $k => $v) {
                    if (is_string($k) && in_array(strtolower($k), self::FORBIDDEN_KEYS, true)) {
                        /* {$k} با آکولاد: در «$k»، PHP نویسه‌ی » را جزء اسم متغیر می‌خواند
                           (اسم متغیر در PHP نویسه‌ی غیرلاتین می‌پذیرد) و به‌جای این خطا
                           «Undefined variable $k»» می‌داد — تست امنیتی گرفتش. */
                        throw new \RuntimeException(
                            "کلید پاسخ در payload محتوا پیدا شد: «{$k}». پاسخ به مرورگر داده نشد."
                        );
                    }
                    $walk($v);
                }
            }
        };
        $walk($payload);
    }
}
