<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * اعداد و کلمه‌های صفحه‌ی لندینگ — از خود بانک، نه دستی.
 *
 * تا پیش از این، «۷۵ دفترچه»، «۲۵ سال»، بازه‌ی «۱۳۸۱ تا ۱۴۰۵» و آن ۲۴ کلمه‌ی
 * نمونه‌ی داخل public/js/landing.js همه در کد نوشته شده بودند. یعنی با هر
 * همگام‌سازی محتوا، صفحه‌ی اول سایت یواشکی از واقعیت عقب می‌افتاد و کسی هم
 * خبردار نمی‌شد. حالا همه‌شان از word_occurrences می‌آیند.
 *
 * کش نیم‌ساعته: این اعداد فقط با sync عوض می‌شوند، ولی لندینگ صفحه‌ی پربازدید
 * مهمان‌هاست و نباید هر بار چند کوئری سنگین بزند. سینک پنل مدیریت بعد از
 * پایان کار خودش این کلیدها را پاک می‌کند.
 */
class LandingFacts
{
    public const CACHE_KEYS = ['zaban.landing.facts', 'zaban.landing.words', 'zaban.landing.sample'];

    /** سطح فارسی ← همان اندیسی که landing.js انتظار دارد (۰ ساده … ۲ پیشرفته) */
    private const LEVEL = ['ساده' => 0, 'متوسط' => 1, 'پیشرفته' => 2];

    /**
     * @return array{booklets:int, years:int, exams:int, sections:int, y1:?int, y2:?int, words:int}
     */
    public function facts(): array
    {
        return Cache::remember('zaban.landing.facts', 1800, function () {
            $r = DB::table('word_occurrences')
                ->selectRaw('count(distinct year) as years, count(distinct exam) as exams,
                             count(distinct section) as sections, count(distinct word_id) as words,
                             min(year) as y1, max(year) as y2')
                ->first();

            /* «دفترچه» = هر ترکیب سال+رشته، نه سال تنها: یک سال سه دفترچه دارد
               و بعضی سال‌ها بعضی رشته‌ها هنوز نبوده‌اند، پس ضرب ساده غلط است. */
            $booklets = DB::table('word_occurrences')
                ->distinct()->count(DB::raw('concat(year, "-", exam)'));

            return [
                'booklets' => (int) $booklets,
                'years'    => (int) ($r->years ?? 0),
                'exams'    => (int) ($r->exams ?? 0),
                'sections' => (int) ($r->sections ?? 0),
                'words'    => (int) ($r->words ?? 0),
                'y1'       => isset($r->y1) ? (int) $r->y1 : null,
                'y2'       => isset($r->y2) ? (int) $r->y2 : null,
            ];
        });
    }

    /**
     * پرتکرارترین کلمه‌ها، به همان شکلی که landing.js می‌خواهد:
     *   [کلمه, معنی, نقش, سطح(۰..۲), رشته‌ی صفر و یکِ سال‌ها]
     *
     * رشته‌ی آخر از y1 تا y2 است — هر خانه یک سال، ۱ یعنی آن سال آمده. طول
     * رشته با تعداد خانه‌های نوار صفحه یکی است، وگرنه نوار ناقص کشیده می‌شود.
     *
     * @return list<array{0:string,1:string,2:string,3:int,4:string}>
     */
    public function words(int $limit = 12): array
    {
        return Cache::remember('zaban.landing.words', 1800, function () use ($limit) {
            $f = $this->facts();
            if (!$f['y1'] || !$f['y2']) return [];

            /* فقط کلمه‌هایی که معنی دارند — روی لندینگ کارت بی‌معنی بد است */
            $rows = DB::table('words')
                ->whereNotNull('meaning_fa')->where('meaning_fa', '!=', '')
                ->orderByDesc('year_count')->orderByDesc('occurrence_count')
                ->limit($limit)
                ->get(['id', 'word', 'meaning_fa', 'pos_primary', 'level']);

            if ($rows->isEmpty()) return [];

            $years = DB::table('word_occurrences')
                ->whereIn('word_id', $rows->pluck('id'))
                ->distinct()->get(['word_id', 'year'])
                ->groupBy('word_id')->map(fn ($g) => $g->pluck('year')->all());

            $out = [];
            foreach ($rows as $w) {
                $has = array_flip($years[$w->id] ?? []);
                $bits = '';
                for ($y = $f['y1']; $y <= $f['y2']; $y++) $bits .= isset($has[$y]) ? '1' : '0';

                /* معنی‌ها با « / » به هم چسبیده‌اند؛ روی کارت فقط اولی جا می‌شود */
                $mean = trim(explode('/', $w->meaning_fa)[0]);

                $out[] = [
                    $w->word,
                    $mean,
                    strtolower((string) ($w->pos_primary ?: '')),
                    self::LEVEL[$w->level] ?? 1,
                    $bits,
                ];
            }
            return $out;
        });
    }

    /**
     * دفترچه‌ی نمونه‌ی لندینگ — سؤال‌های واقعی، بدون کلید پاسخ.
     *
     * تا این نسخه، پاسخ‌برگ لندینگ فقط حباب‌های گزینه بود و هیچ متن سؤالی
     * نداشت؛ روی موبایل که فقط همان کارت دیده می‌شود، ناقص به نظر می‌رسید.
     *
     * دو تصمیم عمدی:
     *   ۱) فقط سؤال‌های «وکب». کلوز و پسیج بدون متنشان بی‌معنی‌اند و متن
     *      کامل جای دیگری است — در خود پلتفرم.
     *   ۲) کلید پاسخ اینجا نمی‌آید. صفحه‌ی عمومی است و پاسخ درست، کارنامه و
     *      تشریح همگی داخل پلتفرم‌اند.
     *
     * دفترچه از میان دفترچه‌های آزمایشی مدیر انتخاب می‌شود (همان‌هایی که در
     * نسخه‌ی نمایشی هم باز است)، وگرنه تازه‌ترین دفترچه‌ی مهندسی کامپیوتر.
     *
     * @return array{year:?int, exam:?string, struct:list<array{label:string,from:int,to:int}>,
     *               questions:list<array{n:int, stem:string, opts:list<string>}>, total:int}
     */
    public function sampleBooklet(int $limit = 8): array
    {
        return Cache::remember('zaban.landing.sample', 1800, function () use ($limit) {
            $pick = null;
            foreach (app(Entitlements::class)->trialBooklets() as $b) {
                if (DB::table('questions')->where(['year' => $b['year'], 'exam' => $b['exam']])->exists()) {
                    $pick = $b; break;
                }
            }
            if (!$pick) {
                $row = DB::table('questions')->where('exam', 'ce')
                    ->orderByDesc('year')->first(['year', 'exam']);
                if (!$row) return ['year' => null, 'exam' => null, 'struct' => [], 'questions' => [], 'total' => 0];
                $pick = ['year' => (int) $row->year, 'exam' => $row->exam];
            }

            $all = DB::table('questions')->where(['year' => $pick['year'], 'exam' => $pick['exam']])
                ->orderBy('question_number')
                ->get(['id', 'question_number', 'section', 'passage_number', 'stem']);

            /* نوار ساختار دفترچه — از خود داده، نه عددهای ثابت داخل قالب */
            $struct = [];
            foreach ($all as $q) {
                $label = (ContentBuilder::SEC_FA[$q->section] ?? $q->section)
                       . ($q->section === 'passage' && $q->passage_number ? ' ' . $q->passage_number : '');
                $n = (int) $q->question_number;
                if (!isset($struct[$label])) $struct[$label] = ['label' => $label, 'from' => $n, 'to' => $n];
                else $struct[$label]['to'] = max($struct[$label]['to'], $n);
            }

            $vocab = $all->where('section', 'vocab')->take($limit)->values();
            $opts  = [];
            if ($vocab->isNotEmpty()) {
                /* is_correct عمداً خوانده نمی‌شود — این خروجی عمومی است */
                DB::table('question_options')->whereIn('question_id', $vocab->pluck('id'))
                    ->orderBy('question_id')->orderBy('position')
                    ->get(['question_id', 'position', 'body'])
                    ->each(function ($o) use (&$opts) { $opts[$o->question_id][] = (string) $o->body; });
            }

            $questions = [];
            foreach ($vocab as $q) {
                $o = $opts[$q->id] ?? [];
                if (count($o) !== 4 || trim((string) $q->stem) === '') continue;
                $questions[] = ['n' => (int) $q->question_number, 'stem' => (string) $q->stem, 'opts' => $o];
            }

            return [
                'year'      => (int) $pick['year'],
                'exam'      => ContentBuilder::EXAM_FA[$pick['exam']] ?? $pick['exam'],
                'struct'    => array_values($struct),
                'questions' => $questions,
                'total'     => $all->count(),
            ];
        });
    }
}
