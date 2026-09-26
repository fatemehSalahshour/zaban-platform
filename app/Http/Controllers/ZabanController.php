<?php

namespace App\Http\Controllers;

use App\Services\ContentBuilder;
use App\Services\Entitlements;
use App\Services\Fsrs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ZabanController extends Controller
{
    /** سقف روزانه‌ی زمان مطالعه — سمت سرور اعمال می‌شود، نه در جاوااسکریپت. */
    private const DAILY_STUDY_CAP = 240 * 60;
    private const HEARTBEAT_STEP  = 30;
    private const HEARTBEAT_MIN_GAP = 25;

    public function __construct(
        private ContentBuilder $content,
        private Entitlements $ent,
        private Fsrs $fsrs,
        private \App\Services\Security\AnswerQuota $quota,
        private \App\Services\Security\WordQuota $wordQuota,
        private \App\Services\Security\AbuseGuard $guard,
        private \App\Services\Security\MeaningQuota $meaningQuota,
        private \App\Services\Security\Settings $secSettings,
        private \App\Services\Security\Watermark $wm,
    ) {}

    /**
     * پاسخ درهم‌شده برای تب Network (امنیت محتوا، قدم ۴): اگر رابط سرآیند X-Zaban-Obf
     * فرستاده باشد، JSON با کلید همین نشست XOR و base64 می‌شود. صادقانه: کلید در
     * مرورگر است؛ این فقط جلوی «باز کردن تب Network و خواندن» را می‌گیرد.
     */
    private function obf(Request $req, array $data): JsonResponse
    {
        $key = $req->hasSession() ? (string) $req->session()->get('obf_key', '') : '';
        if ($req->header('X-Zaban-Obf') !== '1' || $key === '') return response()->json($data);

        $plain = json_encode($data, JSON_UNESCAPED_UNICODE);
        $k = hex2bin($key); $kl = strlen($k); $out = '';
        for ($i = 0, $n = strlen($plain); $i < $n; $i++) $out .= $plain[$i] ^ $k[$i % $kl];
        return response()->json(['o' => base64_encode($out)]);
    }

    /* =================================================================
     |  محتوا
     * ================================================================= */

    /**
     * GET /api/content?exam=ce
     *
     * middleware زبان (zaban.exam) قبلاً چک کرده که کاربر این رشته را دارد
     * و رشته‌ی نرمال‌شده را در attributes گذاشته است.
     */
    public function content(Request $req)
    {
        $exam = $req->attributes->get('zaban_exam');
        $year = $req->attributes->get('zaban_year');          /* دمو: فقط این سال؛ null = کامل */
        return $this->cached($req, $exam, 'core' . ($year ? "-y$year" : ''),
                             fn () => $this->content->core($exam, $year));
    }

    /**
     * GET /api/content/questions?exam=ce
     *
     * جدا شد چون بزرگ‌ترین بخش payload بود و صفحه‌ی اول لازمش ندارد.
     * مرورگر بعد از رندر شدن رابط، در پس‌زمینه می‌گیردش.
     */
    public function contentQuestions(Request $req)
    {
        $exam = $req->attributes->get('zaban_exam');
        $year = $req->attributes->get('zaban_year');
        return $this->cached($req, $exam, 'questions' . ($year ? "-y$year" : ''),
                             fn () => $this->content->questions($exam, $year));
    }

    /**
     * ETag و ۳۰۴.
     *
     * نکته‌ی ظریف: هدر «no-cache» است نه «max-age=3600».
     * با max-age مرورگر تا یک ساعت اصلاً از سرور نمی‌پرسد، یعنی اگر دسترسی
     * کاربر لغو شود یا برگشت وجه بخورد، تا یک ساعت محتوا را دارد.
     * با no-cache هر بار می‌پرسد، ولی جوابش معمولاً ۳۰۴ خالی است —
     * یک رفت‌وبرگشت ناچیز در ازای اینکه لغو دسترسی واقعاً کار کند.
     */
    private function cached(Request $req, string $exam, string $part, callable $build)
    {
        $etag = $this->content->etag($exam, $part);

        $headers = [
            'ETag'          => $etag,
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Vary'          => 'Accept-Encoding',
        ];

        if (trim((string) $req->header('If-None-Match')) === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        $payload = $build();
        $this->content->assertNoAnswerKey($payload);   // شبکه‌ی ایمنی

        return response()->json($payload, 200, $headers);
    }

    /* =================================================================
     |  کلید پاسخ — حساس‌ترین مسیر پروژه
     * ================================================================= */

    /**
     * GET /api/question/{id}/answer
     *
     * سه شرط مجاز بودن، به همین ترتیب:
     *   ۱) کاربر رشته‌ی این سؤال را خریده باشد            → وگرنه ۴۰۳
     *   ۲) اگر آزمون بازِ فیدبک روی همین سال/رشته دارد    → ۴۰۹، پاسخ نمی‌دهیم
     *   ۳) در غیر این صورت مجاز: واشبک، آزمون تمام‌شده، یا مرور آزاد
     *
     * هر افشا در answer_reveals لاگ می‌شود. اگر کسی بخواهد پاسخ‌نامه را
     * تک‌تک جمع کند، در همان جدول دیده می‌شود.
     */
    public function answer(Request $req, int $id): JsonResponse
    {
        $user = $req->user();

        $q = DB::table('questions')->where('id', $id)
            ->first(['id', 'year', 'exam', 'correct_option', 'explanation']);

        if (!$q) return response()->json(['error' => 'not_found'], 404);

        // ۱) حق دسترسی: رشته‌ی خریده‌شده، یا سؤالِ سالِ دمو
        if (!$this->ent->canItem($user->id, $q->exam, (int) $q->year)) {
            return response()->json([
                'error' => 'not_entitled', 'exam' => $q->exam,
                'message' => 'دسترسی این رشته فعال نیست.',
                'buy_url' => '/buy?exam=' . $q->exam,
            ], 403);
        }

        // ۲) آزمون باز — قاعده‌ی قفل در answerGate، مشترک با مسیر دسته‌ای
        $gate = $this->answerGate($user->id, $q, $this->openAttempt($user->id));
        if ($gate === null) {
            // حالت فیدبک: پاسخ فقط سر کارنامه می‌آید.
            return response()->json([
                'error'   => 'answer_locked',
                'message' => 'در حالت فیدبک، پاسخ بعد از پایان آزمون نشان داده می‌شود.',
            ], 409);
        }

        /* سقف روزانه — سؤالی که امروز دیده شده دوباره شمرده نمی‌شود */
        $qa = $this->quota->allow($user->id, [$id]);
        if ($qa['limited']) {
            return response()->json(['error' => 'daily_limit', 'message' => $this->quota->message()], 429);
        }
        DB::table('answer_reveals')->insert([
            'user_id' => $user->id, 'question_id' => $id,
            'reason' => $gate[0], 'attempt_id' => $gate[1], 'created_at' => now(),
        ]);
        $this->guard->afterAnswerReveal($user->id);          /* الگوی اسکریپت ← قفل خودکار */

        return response()->json([
            'correct'     => (int) $q->correct_option,
            'explanation' => $this->wm->mark($user->id, $q->explanation),
            'words'       => $this->questionWords($id),
        ]);
    }

    /**
     * پاسخ چند سؤال با یک درخواست: GET /api/answers?ids=1,2,3 (حداکثر ۴۰).
     *
     * برای بازکردن کارنامه‌ی یک آزمون قدیمی یا حالت واشبک بعد از رفرش لازم
     * است؛ سی درخواست تکی به سقف throttle می‌خورد. قاعده‌ها همان مسیر تکی‌اند:
     * سؤال رشته‌ی خریداری‌نشده بی‌صدا حذف می‌شود و سؤالِ آزمون فیدبکِ باز
     * در locked برمی‌گردد.
     */
    public function answers(Request $req): JsonResponse
    {
        $ids = collect(explode(',', (string) $req->query('ids', '')))
            ->map(fn ($x) => (int) $x)->filter(fn ($x) => $x > 0)
            ->unique()->take(40)->values();

        if ($ids->isEmpty()) {
            return response()->json(['answers' => [], 'locked' => []]);
        }

        $uid = $req->user()->id;
        /* فقط سؤال‌های رشته‌ی خریده‌شده یا سالِ دمو */
        $qs = $this->ent->scopeQuery(DB::table('questions')->whereIn('id', $ids), $uid)
            ->get(['id', 'year', 'exam', 'correct_option', 'explanation']);

        $open = $this->openAttempt($uid);
        $out = []; $locked = []; $log = []; $ok = [];

        foreach ($qs as $q) {
            $gate = $this->answerGate($uid, $q, $open);
            if ($gate === null) { $locked[] = (int) $q->id; continue; }
            $ok[(int) $q->id] = [$q, $gate];
        }

        /* سقف روزانه روی آنچه قفل نیست */
        $qa = $this->quota->allow($uid, array_keys($ok));
        $fresh = array_flip($qa['fresh']);
        foreach ($qa['allowed'] as $qid) {
            [$q, $gate] = $ok[$qid];
            if (isset($fresh[$qid])) {
                $log[] = ['user_id' => $uid, 'question_id' => $qid, 'reason' => $gate[0],
                          'attempt_id' => $gate[1], 'created_at' => now()];
            }
            $out[] = ['id' => $qid, 'correct' => (int) $q->correct_option,
                      'explanation' => $this->wm->mark($uid, $q->explanation)];
        }

        if ($log) {
            DB::table('answer_reveals')->insert($log);
            $this->guard->afterAnswerReveal($uid);
        }

        return $this->obf($req, ['answers' => $out, 'locked' => $locked, 'limited' => $qa['limited'],
                                 'limit_message' => $qa['limited'] ? $this->quota->message() : null]);
    }

    /* =================================================================
     |  آمار سؤال‌ها و کلمات
     * ================================================================= */

    /** کمترین تعداد پاسخ تا آماری نشان داده شود — جلوی لو رفتن پاسخ یک نفر. */
    private function crowdMin(): int
    {
        return app()->environment('local') ? 1 : 5;
    }

    /**
     * GET /api/crowd — آمار جمعی، فقط برای رشته‌هایی که کاربر خریده.
     *   q: { qid: [تعداد پاسخ, درست, نزده] }   w: { wid: [تعداد نفر, بلد بوده‌اند] }
     *
     * توزیع گزینه‌ها («کدام گزینه را بیشتر زده‌اند») عمداً اینجا نیست: با آن
     * می‌شد پاسخ همه‌ی سؤال‌ها را از یک درخواست حدس زد. آن فقط برای یک سؤال
     * و پشت همان قفل پاسخ، از /api/question/{id}/stats می‌آید.
     */
    public function crowd(Request $req): JsonResponse
    {
        $q = []; $w = [];
        $ttl = app()->environment('local') ? 5 : 600;
        foreach ($this->ent->for($req->user()->id) as $code) {
            $part = cache()->remember("zaban.crowd.$code", $ttl, fn () => $this->crowdFor($code));
            $q += $part['q'];
            $w += $part['w'];         // کلمه‌ی مشترک دو رشته: عددش یکی است
        }
        return response()->json(['q' => (object) $q, 'w' => (object) $w, 'min' => $this->crowdMin()]);
    }

    private function crowdFor(string $code): array
    {
        $min = $this->crowdMin();

        $q = DB::table('exam_answers as a')
            ->join('exam_attempts as t', 't.id', '=', 'a.attempt_id')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->whereNotNull('t.finished_at')->where('q.exam', $code)
            ->groupBy('a.question_id')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->selectRaw('a.question_id, COUNT(*) AS n, SUM(a.is_correct = 1) AS r, SUM(a.chosen IS NULL) AS b')
            ->get()
            ->mapWithKeys(fn ($x) => [(int) $x->question_id => [(int) $x->n, (int) $x->r, (int) $x->b]])
            ->all();

        /* هر نفر یک بار: آخرین امتیاز هر کاربر روی هر کلمه. کسی که ده بار
           «یادم نبود» زده و آخرش یاد گرفته، یک نفرِ «بلد» است. */
        $last = DB::table('review_logs')
            ->where('item_type', 'word')
            ->whereIn('item_id', DB::table('word_occurrences')->where('exam', $code)->select('word_id'))
            ->groupBy('user_id', 'item_id')
            ->selectRaw('MAX(id) AS mid');

        $w = DB::table('review_logs as l')
            ->joinSub($last, 'm', 'm.mid', '=', 'l.id')
            ->groupBy('l.item_id')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->selectRaw('l.item_id, COUNT(*) AS n, SUM(l.rating > 1) AS ok')
            ->get()
            ->mapWithKeys(fn ($x) => [(int) $x->item_id => [(int) $x->n, (int) $x->ok]])
            ->all();

        return ['q' => $q, 'w' => $w];
    }

    /**
     * GET /api/question/{id}/stats — آمار کامل یک سؤال، با توزیع گزینه‌ها.
     * همان قاعده‌های مسیر پاسخ: رشته‌ی خریده‌شده، و قفل آزمون فیدبکِ باز.
     */
    public function questionStats(Request $req, int $id): JsonResponse
    {
        $uid = $req->user()->id;
        $q = DB::table('questions')->where('id', $id)->first(['id', 'year', 'exam', 'correct_option']);
        if (!$q) return response()->json(['error' => 'not_found'], 404);
        if (!$this->ent->canItem($uid, $q->exam, (int) $q->year)) return response()->json(['error' => 'not_entitled'], 403);
        if ($this->answerGate($uid, $q, $this->openAttempt($uid)) === null) {
            return response()->json(['error' => 'answer_locked'], 409);
        }

        $rows = DB::table('exam_answers as a')
            ->join('exam_attempts as t', 't.id', '=', 'a.attempt_id')
            ->whereNotNull('t.finished_at')->where('a.question_id', $id)
            ->groupBy('a.chosen')->selectRaw('a.chosen, COUNT(*) AS n')->pluck('n', 'chosen');

        $opt = [0, 0, 0, 0]; $blank = 0; $total = 0;
        foreach ($rows as $chosen => $n) {
            $total += (int) $n;
            if ($chosen === '' || $chosen === null) { $blank += (int) $n; continue; }
            if ($chosen >= 1 && $chosen <= 4) $opt[(int) $chosen - 1] += (int) $n;
        }
        if ($total < $this->crowdMin()) return response()->json(['total' => 0]);

        $right = $opt[(int) $q->correct_option - 1] ?? 0;
        return response()->json([
            'total' => $total, 'right' => $right, 'blank' => $blank,
            'wrong' => $total - $right - $blank, 'opt' => $opt,
        ]);
    }

    /** POST /api/question/{id}/attempt — یک بار زدن سؤال در حالت تمرین. درستی را سرور حساب می‌کند. */
    public function questionAttempt(Request $req, int $id): JsonResponse
    {
        $d = $req->validate(['chosen' => ['required', 'integer', 'between:1,4']]);
        $uid = $req->user()->id;

        $q = DB::table('questions')->where('id', $id)->first(['id', 'year', 'exam', 'correct_option']);
        if (!$q) return response()->json(['error' => 'not_found'], 404);
        if (!$this->ent->canItem($uid, $q->exam, (int) $q->year)) return response()->json(['error' => 'not_entitled'], 403);
        if ($this->answerGate($uid, $q, $this->openAttempt($uid)) === null) {
            return response()->json(['error' => 'answer_locked'], 409);
        }

        DB::table('question_attempts')->insert([
            'user_id' => $uid, 'question_id' => $id, 'chosen' => $d['chosen'],
            'is_correct' => (int) $d['chosen'] === (int) $q->correct_option, 'created_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/words/detail?ids=1,2,3 (حداکثر ۴۰) — مثال‌های کلمه‌ها.
     * فقط کلمه‌ی رشته‌ی خریده‌شده یا سالِ دمو؛ سقف روزانه‌ی کلمه (WordQuota).
     *   { items:[{id, ex:[[جمله, ترجمه]]}], limited:[id], limit_message }
     */
    public function wordDetails(Request $req): JsonResponse
    {
        $ids = collect(explode(',', (string) $req->query('ids', '')))
            ->map(fn ($x) => (int) $x)->filter(fn ($x) => $x > 0)->unique()->take(40)->values()->all();
        $uid = $req->user()->id;

        $ok = $this->filterEntitledItems($uid, 'word', $ids);
        $qa = $this->wordQuota->allow($uid, $ok);
        if ($qa['fresh']) {
            DB::table('word_reveals')->insert(array_map(
                fn ($w) => ['user_id' => $uid, 'word_id' => $w, 'created_at' => now()], $qa['fresh']));
        }
        /* الگوی اسکریپت (سرعت، یا سقف روزانه در چند روز پیاپی) ← قفل خودکار */
        if ($qa['fresh'] || $qa['limited']) $this->guard->afterWordReveal($uid);

        $ex = $this->content->examples($qa['allowed']);
        $sig = fn (array $pairs) => array_map(fn ($p) => [$this->wm->mark($uid, $p[0]), $this->wm->mark($uid, $p[1])], $pairs);
        return $this->obf($req, [
            'items'         => array_map(fn ($w) => ['id' => $w, 'ex' => $sig($ex[$w] ?? [])], $qa['allowed']),
            'limited'       => $qa['limited'],
            'limit_message' => $qa['limited'] ? $this->wordQuota->message() : null,
        ]);
    }

    /**
     * GET /api/words/meanings?ids=1,2,3 (حداکثر ۶۰) — معنی کلمه‌ها (امنیت محتوا، قدم ۳).
     *   { items:[{id, fa}], limited:[id], limit_message }
     */
    public function wordMeanings(Request $req): JsonResponse
    {
        $ids = collect(explode(',', (string) $req->query('ids', '')))
            ->map(fn ($x) => (int) $x)->filter(fn ($x) => $x > 0)->unique()->take(60)->values()->all();
        $uid = $req->user()->id;

        $ok = $this->filterEntitledItems($uid, 'word', $ids);
        $qa = $this->meaningQuota->allow($uid, $ok);
        if ($qa['fresh']) {
            DB::table('meaning_reveals')->insert(array_map(
                fn ($w) => ['user_id' => $uid, 'word_id' => $w, 'created_at' => now()], $qa['fresh']));
        }
        if ($qa['fresh'] || $qa['limited']) $this->guard->afterMeaningReveal($uid);

        $fa = $this->content->meanings($qa['allowed']);
        return $this->obf($req, [
            /* امضای نامرئی شماره‌ی کاربر در هر معنی */
            'items'         => array_map(fn ($w) => ['id' => $w, 'fa' => $this->wm->mark($uid, $fa[$w] ?? '')], $qa['allowed']),
            'limited'       => $qa['limited'],
            'limit_message' => $qa['limited'] ? $this->meaningQuota->message() : null,
        ]);
    }

    /**
     * GET /api/words/search?q=… — جست‌وجوی متن فارسی در معنی‌ها. فقط شناسه برمی‌گرداند
     * (خود معنی از /words/meanings و با سقف). فقط کلمه‌های در دسترس (خرید یا سالِ دمو).
     */
    public function wordSearch(Request $req): JsonResponse
    {
        $q = trim((string) $req->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['ids' => []]);
        $uid = $req->user()->id;

        $inScope = $this->ent->scopeQuery(DB::table('word_occurrences'), $uid)->select('word_id');
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $ids = DB::table('words')->whereIn('id', $inScope)->where('meaning_fa', 'like', $like)
            ->limit(300)->pluck('id')->map(fn ($x) => (int) $x)->all();

        return response()->json(['ids' => $ids]);
    }

    private function openAttempt(int $uid): ?object
    {
        return DB::table('exam_attempts')
            ->where('user_id', $uid)->whereNull('finished_at')
            ->orderByDesc('id')
            ->first(['id', 'year', 'exam', 'mode', 'deadline_at']);
    }

    /**
     * آیا پاسخ این سؤال الان دیدنی است؟
     * null یعنی قفل (آزمون فیدبکِ باز روی همین دفترچه)؛
     * وگرنه [دلیل برای لاگ, شناسه‌ی آزمون مرتبط].
     */
    private function answerGate(int $uid, object $q, ?object $open): ?array
    {
        $reason = 'free'; $attemptId = null;

        if ($open) {
            $sameExam = ((int) $open->year === (int) $q->year) && $open->exam === $q->exam;
            $expired  = $open->deadline_at !== null && now()->gt($open->deadline_at);

            if ($sameExam && !$expired) {
                if ($open->mode !== 'washback') return null;
                $reason = 'washback'; $attemptId = $open->id;
            }
        }

        if ($reason === 'free') {
            // آیا این سؤال در آزمونِ تمام‌شده‌ای از همین کاربر بوده؟ فقط برای لاگ.
            $finished = DB::table('exam_answers as a')
                ->join('exam_attempts as t', 't.id', '=', 'a.attempt_id')
                ->where('t.user_id', $uid)->whereNotNull('t.finished_at')
                ->where('a.question_id', $q->id)->value('a.attempt_id');
            if ($finished) { $reason = 'finished'; $attemptId = $finished; }
        }

        return [$reason, $attemptId];
    }

    private function questionWords(int $qid): array
    {
        $out = ['option' => [], 'stem' => [], 'passage' => []];
        DB::table('question_words')->where('question_id', $qid)
            ->get(['word_id', 'role'])
            ->each(function ($r) use (&$out) {
                $role = $r->role === 'answer' ? 'option' : $r->role;
                if (isset($out[$role])) $out[$role][] = (int) $r->word_id;
            });
        return array_map(fn ($a) => array_values(array_unique($a)), $out);
    }

    /* =================================================================
     |  وضعیت کاربر
     * ================================================================= */

    public function me(Request $req): JsonResponse
    {
        $user  = $req->user();
        $owned = $this->ent->for($user->id);

        $profile = DB::table('zaban_profiles')->where('user_id', $user->id)->first();

        /* رابط، سؤال را با کلید «سال|رشته|شماره» می‌شناسد نه با id. سؤال‌ها
           با تأخیر بارگذاری می‌شوند، پس رابط موقع باز شدن نمی‌تواند id را
           به کلید تبدیل کند — کلید را همین‌جا می‌سازیم و کنار id می‌فرستیم. */
        $qIds = DB::table('deck_items')->where('user_id', $user->id)->where('item_type', 'question')->pluck('item_id')
            ->merge(DB::table('user_stars')->where('user_id', $user->id)->where('item_type', 'question')->pluck('item_id'))
            ->merge(DB::table('user_notes')->where('user_id', $user->id)->where('item_type', 'question')->pluck('item_id'))
            ->unique()->values();
        $qKey = $qIds->isEmpty() ? collect() : DB::table('questions')->whereIn('id', $qIds)
            ->get(['id', 'year', 'exam', 'question_number'])
            ->mapWithKeys(fn ($q) => [(int) $q->id =>
                $q->year . '|' . (ContentBuilder::EXAM_FA[$q->exam] ?? $q->exam) . '|' . (int) $q->question_number]);

        $deck = DB::table('deck_items')->where('user_id', $user->id)->get()
            ->map(fn ($d) => [
                't' => $d->item_type === 'word' ? 'w' : 'q', 'id' => (int) $d->item_id,
                'k' => $d->item_type === 'word' ? null : ($qKey[(int) $d->item_id] ?? null),
                's' => $d->stability !== null ? (float) $d->stability : null,
                'dif' => $d->difficulty !== null ? (float) $d->difficulty : null,
                'ef' => (float) $d->ease_factor, 'iv' => (int) $d->interval_days,
                'reps' => (int) $d->reps, 'lapses' => (int) $d->lapses,
                'seen' => (int) $d->seen_count, 'again' => (int) $d->again_count,
                'due' => $d->due_date, 'mastered_at' => $d->mastered_at,
                'last' => $d->last_review_at ? substr((string) $d->last_review_at, 0, 10) : null,
            ])->values();

        $notes = DB::table('user_notes')->where('user_id', $user->id)->get()
            ->map(fn ($n) => ['t' => $n->item_type === 'word' ? 'w' : 'q',
                              'id' => (int) $n->item_id, 'body' => $n->body,
                              'k' => $n->item_type === 'word' ? null : ($qKey[(int) $n->item_id] ?? null)])->values();

        $open = DB::table('exam_attempts')->where('user_id', $req->user()->id)
            ->whereNull('finished_at')->orderByDesc('id')->first();

        // آزمونی که مهلتش گذشته «باز» نیست — کارت زرد اشتباه نشان ندهیم.
        if ($open && $open->deadline_at !== null && now()->gt($open->deadline_at)) {
            $open = null;
        }

        return response()->json([
            /* تاریخ کنکور (میلادی) — شمارش معکوس و برنامه‌ی روزانه‌ی داشبورد. همان
               تاریخی که مدیر در پنل می‌گذارد و پایان دسترسی خریدها هم هست. */
            'exam_date' => app(\App\Services\Pricing::class)->accessUntil(),
            /* سوییچر «پلتفرم‌ها» بالای صفحه — آدرس‌ها از config/platforms.php و .env */
            'platforms' => ['current' => config('platforms.current'), 'list' => array_map(function ($p) {
                /* آدرس بی‌پروتکل در .env («plan.konkurcomputer.ir/...») را https می‌کنیم؛ رابط فقط
                   http/https را لینک می‌کند و بقیه را «بی‌آدرس» (کم‌رنگ) نشان می‌داد. */
                $u = trim((string) ($p['url'] ?? ''), " \t\n\r\0\x0B\"'");
                if ($u !== '' && !preg_match('~^https?://~i', $u)) $u = 'https://' . ltrim($u, '/');
                $p['url'] = $u ?: null;
                return $p;
            }, config('platforms.list', []))],
            /* شناسه‌ی کاربر — رابط با آن می‌فهمد داده‌ی ذخیره‌شده در مرورگر مال همین نفر است */
            'uid' => (int) $user->id,
            /* کلید درهم‌سازی پاسخ‌ها برای همین نشست (obf) */
            'ok'  => $req->hasSession()
                ? ($req->session()->get('obf_key') ?: tap(bin2hex(random_bytes(16)), fn ($k) => $req->session()->put('obf_key', $k)))
                : null,
            /* فهرست‌ها با معنی؟ (پنل مدیریت ← امنیت محتوا) */
            'list_meanings' => $this->secSettings->listMeanings(),
            /* سقف کارت تازه در روز: انتخاب خود دانشجو، وگرنه پیش‌فرض مدیر */
            'new_per_day'   => (int) ($profile->new_per_day ?? 0) ?: $this->secSettings->newPerDay(),
            'profile' => [
                'nickname' => $profile->nickname ?? null,
                /* رشته‌ی پیش‌فرض: خریده‌شده؛ وگرنه رشته‌ی دمو (اول رشته‌ی پروفایل) */
                'exam'     => $this->ent->defaultExam($user->id, $profile->exam ?? null)
                    ?? (function () use ($user, $profile) {
                        $d = $this->ent->demoExams($user->id);
                        return in_array($profile->exam ?? null, $d, true) ? $profile->exam : ($d[0] ?? null);
                    })(),
                'owned'    => $owned,
                /* دمو: رشته‌هایی که نخریده ولی سالِ دموی آن‌ها باز است */
                'demo'     => ($dy = $this->ent->demoYear())
                    ? ['year' => $dy, 'exams' => $this->ent->demoExams($user->id)]
                    : null,
                'show_in_board' => (bool) ($profile->show_in_board ?? true),
                /* از حساب کاربر — فقط نمایش. موبایل فقط اگر ستونش باشد (بعد از SSO). */
                'name'       => $user->name ?? null,
                'mobile'     => Schema::hasColumn('users', 'mobile') ? ($user->mobile ?? null) : null,
                'university' => $profile->university ?? null,
                'gpa'        => isset($profile->gpa) && $profile->gpa !== null ? (float) $profile->gpa : null,
                'quota'      => $profile->quota ?? null,
                'degree'     => $profile->degree ?? null,
                'is_admin' => in_array($user->type ?? 'student', ['admin', 'manager', 'editor'], true),
            ],
            'deck'  => $deck,
            /* تلاش‌های تمرینی خود کاربر روی هر سؤال — «این تست را n بار زده‌اید» */
            'qatt' => rescue(fn () => DB::table('question_attempts as a')->join('questions as q', 'q.id', '=', 'a.question_id')
                ->where('a.user_id', $user->id)
                ->groupBy('a.question_id', 'q.year', 'q.exam', 'q.question_number')
                ->selectRaw('q.year, q.exam, q.question_number, COUNT(*) AS n, SUM(a.is_correct) AS ok,
                             SUM(a.chosen = 1) AS o1, SUM(a.chosen = 2) AS o2, SUM(a.chosen = 3) AS o3, SUM(a.chosen = 4) AS o4')
                ->get()->map(fn ($x) => [
                    'k' => $x->year . '|' . (ContentBuilder::EXAM_FA[$x->exam] ?? $x->exam) . '|' . (int) $x->question_number,
                    'n' => (int) $x->n, 'ok' => (int) $x->ok,
                    'opt' => [(int) $x->o1, (int) $x->o2, (int) $x->o3, (int) $x->o4],
                ])->values(), []),
            /* فعالیت روزانه‌ی یک سال اخیر — ردیف «شما» در رتبه‌بندی و روند هفتگی
               داشبورد از همین ساخته می‌شوند. قبلاً فقط از حافظه‌ی مرورگر می‌آمد
               و در مرورگر دیگر همه صفر بود. */
            'activity' => rescue(fn () => DB::table('daily_activity')->where('user_id', $user->id)
                ->where('day', '>=', now()->subDays(370)->toDateString())
                ->get(['day', 'study_sec', 'reviews', 'reviews_ok', 'questions', 'questions_ok', 'mastered'])
                ->map(fn ($a) => [
                    'd' => substr((string) $a->day, 0, 10),
                    'sec' => (int) $a->study_sec, 'rev' => (int) $a->reviews, 'revOk' => (int) $a->reviews_ok,
                    'q' => (int) $a->questions, 'qOk' => (int) $a->questions_ok, 'mast' => (int) $a->mastered,
                ])->values(), []),
            /* چند کارت امروز برای اولین بار مرور شده — سقف «کارت تازه در روز»
               باید بین همه‌ی مرورگرها و دستگاه‌ها یکی باشد. */
            'new_today' => rescue(fn () => DB::table('review_logs')->where('user_id', $user->id)
                ->select('item_type', 'item_id')->groupBy('item_type', 'item_id')
                ->havingRaw('MIN(created_at) >= ?', [now()->startOfDay()])
                ->get()->count(), 0),
            'star'  => DB::table('user_stars')->where('user_id', $user->id)->get()
                        ->map(fn ($s) => ['t' => $s->item_type === 'word' ? 'w' : 'q',
                                          'id' => (int) $s->item_id,
                                          'k' => $s->item_type === 'word' ? null : ($qKey[(int) $s->item_id] ?? null)])->values(),
            'notes' => $notes,
            'reading' => DB::table('reading_marks')->where('user_id', $user->id)
                            ->pluck('position', 'slot'),
            'openAttempt' => $open ? [
                'id' => (int) $open->id, 'year' => (int) $open->year, 'exam' => $open->exam,
                'mode' => $open->mode, 'deadline_at' => $open->deadline_at,
                'state' => json_decode($open->state_json ?: '{}', true),
            ] : null,
        ]);
    }

    /** PUT /api/profile — پروفایل کاربر. نام و موبایل از حساب کاربر است و اینجا عوض نمی‌شود. */
    public function profile(Request $req): JsonResponse
    {
        $uid = $req->user()->id;
        $d = $req->validate([
            /* یکتا بین کاربران (قید یکتای ستون) — بدون این، تکراری خطای ۵۰۰ می‌داد */
            'nickname'      => ['nullable', 'string', 'max:30', 'regex:/^[^<>"&]*$/u',
                                \Illuminate\Validation\Rule::unique('zaban_profiles', 'nickname')->ignore($uid, 'user_id')],
            'exam'          => ['nullable', 'in:ce,it,cs'],
            'show_in_board' => ['required', 'boolean'],
            'university'    => ['nullable', 'string', 'max:150'],
            'gpa'           => ['nullable', 'numeric', 'between:0,20'],
            'quota'         => ['nullable', 'in:free,veteran'],
            'degree'        => ['nullable', 'in:msc,phd'],
            'new_per_day'   => ['nullable', 'integer', 'between:5,100'],
        ], [
            'nickname.unique' => 'این نام مستعار را کاربر دیگری انتخاب کرده است.',
        ], [
            'nickname' => 'نام مستعار', 'gpa' => 'معدل', 'university' => 'دانشگاه',
        ]);

        $row = [
            'nickname'      => isset($d['nickname']) && trim($d['nickname']) !== '' ? trim($d['nickname']) : null,
            'exam'          => $d['exam'] ?? 'ce',        /* ستون NOT NULL با پیش‌فرض ce */
            'show_in_board' => (int) $d['show_in_board'],
            'university'    => $d['university'] ?? null,
            'gpa'           => $d['gpa'] ?? null,
            'quota'         => $d['quota'] ?? null,
            'degree'        => $d['degree'] ?? null,
            'new_per_day'   => $d['new_per_day'] ?? null,        /* NULL = پیش‌فرض مدیر */
            'updated_at'    => now(),
        ];
        if (DB::table('zaban_profiles')->where('user_id', $uid)->exists()) {
            DB::table('zaban_profiles')->where('user_id', $uid)->update($row);
        } else {
            DB::table('zaban_profiles')->insert($row + ['user_id' => $uid, 'created_at' => now()]);
        }

        $p = DB::table('zaban_profiles')->where('user_id', $uid)->first();
        return response()->json(['profile' => [
            'nickname' => $p->nickname, 'exam' => $this->ent->defaultExam($uid, $p->exam),
            'show_in_board' => (bool) $p->show_in_board,
            'university' => $p->university, 'gpa' => $p->gpa !== null ? (float) $p->gpa : null,
            'quota' => $p->quota, 'degree' => $p->degree,
            'new_per_day' => (int) ($p->new_per_day ?? 0) ?: $this->secSettings->newPerDay(),
        ]]);
    }

    public function deck(Request $req): JsonResponse
    {
        $d = $req->validate([
            't'   => 'required|in:w,q',
            'id'  => 'required_without:ids|integer|min:1',
            'ids' => 'array|max:500',
            'ids.*' => 'integer|min:1',
            'on'  => 'required|boolean',
        ]);

        $type = $d['t'] === 'w' ? 'word' : 'question';
        $ids  = $d['ids'] ?? [$d['id']];
        $ids  = $this->filterEntitledItems($req->user()->id, $type, $ids);

        if (!$ids) return response()->json(['ok' => true, 'changed' => 0]);

        if ($d['on']) {
            $rows = array_map(fn ($id) => [
                'user_id' => $req->user()->id, 'item_type' => $type, 'item_id' => $id,
                'ease_factor' => 2.5, 'interval_days' => 0, 'reps' => 0, 'lapses' => 0,
                'due_date' => now()->toDateString(), 'created_at' => now(),
            ], $ids);
            DB::table('deck_items')->insertOrIgnore($rows);
        } else {
            DB::table('deck_items')->where('user_id', $req->user()->id)
                ->where('item_type', $type)->whereIn('item_id', $ids)->delete();
        }

        return response()->json(['ok' => true, 'changed' => count($ids)]);
    }

    public function star(Request $req): JsonResponse
    {
        $d = $req->validate(['t' => 'required|in:w,q', 'id' => 'required|integer|min:1', 'on' => 'required|boolean']);
        $type = $d['t'] === 'w' ? 'word' : 'question';

        if (!$this->filterEntitledItems($req->user()->id, $type, [$d['id']])) {
            return response()->json(['error' => 'not_entitled'], 403);
        }

        $key = ['user_id' => $req->user()->id, 'item_type' => $type, 'item_id' => $d['id']];
        $d['on'] ? DB::table('user_stars')->insertOrIgnore($key + ['created_at' => now()])
                 : DB::table('user_stars')->where($key)->delete();

        return response()->json(['ok' => true]);
    }

    public function note(Request $req): JsonResponse
    {
        $d = $req->validate([
            't' => 'required|in:w,q', 'id' => 'required|integer|min:1',
            // nullable لازم است: لاراول رشته‌ی خالی را null می‌کند
            // (ConvertEmptyStringsToNull) و بدون آن حذف یادداشت ۴۲۲ می‌گرفت.
            'body' => 'present|nullable|string|max:4000',
        ]);
        $d['body'] = (string) ($d['body'] ?? '');
        $type = $d['t'] === 'w' ? 'word' : 'question';

        if (!$this->filterEntitledItems($req->user()->id, $type, [$d['id']])) {
            return response()->json(['error' => 'not_entitled'], 403);
        }

        $key = ['user_id' => $req->user()->id, 'item_type' => $type, 'item_id' => $d['id']];

        if (trim($d['body']) === '') {
            DB::table('user_notes')->where($key)->delete();
        } else {
            DB::table('user_notes')->updateOrInsert($key,
                ['body' => $d['body'], 'updated_at' => now(), 'created_at' => now()]);
        }
        return response()->json(['ok' => true]);
    }

    public function reading(Request $req): JsonResponse
    {
        $d = $req->validate([
            'year' => 'required|integer|min:1380|max:1420',
            'exam' => 'required|in:ce,it,cs',
            'position' => 'required|integer|min:0|max:2000',
        ]);
        if (!$this->ent->canItem($req->user()->id, $d['exam'], (int) $d['year'])) {
            return response()->json(['error' => 'not_entitled'], 403);
        }
        DB::table('reading_marks')->updateOrInsert(
            ['user_id' => $req->user()->id, 'slot' => $d['year'] . '|' . $d['exam']],
            ['position' => $d['position'], 'updated_at' => now()]
        );
        return response()->json(['ok' => true]);
    }

    /* =================================================================
     |  مرور — SM-2 سمت سرور
     * ================================================================= */

    public function review(Request $req): JsonResponse
    {
        $d = $req->validate([
            't' => 'required|in:w,q', 'id' => 'required|integer|min:1',
            'rating' => 'required|integer|min:1|max:4',
            'mode' => 'nullable|string|max:24',
        ]);
        $type = $d['t'] === 'w' ? 'word' : 'question';
        $uid  = $req->user()->id;

        if (!$this->filterEntitledItems($uid, $type, [$d['id']])) {
            return response()->json(['error' => 'not_entitled'], 403);
        }

        return DB::transaction(function () use ($d, $type, $uid) {
            $key = ['user_id' => $uid, 'item_type' => $type, 'item_id' => $d['id']];

            $card = DB::table('deck_items')->where($key)->lockForUpdate()->first();
            if (!$card) {
                DB::table('deck_items')->insert($key + [
                    'stability' => null, 'difficulty' => null,
                    'ease_factor' => 2.5, 'interval_days' => 0, 'reps' => 0, 'lapses' => 0,
                    'due_date' => now()->toDateString(), 'scheduler' => 'fsrs6',
                    'created_at' => now(),
                ]);
                $card = DB::table('deck_items')->where($key)->first();
            }

            // فاصله‌ی واقعی از مرور قبلی — FSRS بدون این نمی‌تواند R را حساب کند.
            // SM-2 این را لازم نداشت؛ همین یکی از دلایل دقت بیشتر FSRS است:
            // زود مرور کردن و دیر مرور کردن اثر متفاوتی دارند.
            // با حساب ثانیه، نه diffInDays: در Carbon 3 (لاراول ۱۱ به بعد)
            // now()->diffInDays(گذشته) منفی برمی‌گرداند و max(0, …) همیشه ۰ می‌شد —
            // یعنی FSRS هر مرور را «همان روز» فرض می‌کرد.
            $elapsed = $card->last_review_at
                ? max(0, intdiv(now()->startOfDay()->timestamp
                    - \Illuminate\Support\Carbon::parse($card->last_review_at)->startOfDay()->timestamp, 86400))
                : 0;

            $before = ($card->stability !== null)
                ? ['stability' => (float) $card->stability, 'difficulty' => (float) $card->difficulty]
                : null;

            /* سقف کنکور و پراکندگی — هر دو باید پیش از review و preview تنظیم شوند،
               وگرنه عددی که در دکمه نشان داده‌ایم با چیزی که ثبت می‌شود فرق می‌کند. */
            /* وزن‌های همین کاربر (اگر بهینه‌ساز ساخته و پذیرفته باشد)، وگرنه
               پیش‌فرض. سؤال‌ها رشته دارند و می‌توانند تنظیم دقیق‌تری بگیرند. */
            $params = app(\App\Services\FsrsParams::class);
            $fsrs   = $params->engine($uid, $params->examOf($type, (int) $d['id']));

            /* کلید پراکندگی = نوع و شناسه‌ی همان آیتم؛ دقیقاً همان چیزی که
               مرورگر هم دارد. اگر اینجا از deck_items.id استفاده می‌کردیم،
               دو طرف دو عدد می‌ساختند و دکمه با آنچه ثبت می‌شود فرق می‌کرد. */
            $fsrs->horizon(app(\App\Services\Pricing::class)->accessUntil())
                 ->seed(($type === 'word' ? 'w' : 'q') . ':' . $d['id']);

            $r = $fsrs->retrievability($elapsed, (float) ($card->stability ?: 0));
            $next = $fsrs->review($before, $d['rating'], $elapsed);

            $justMastered = $card->mastered_at === null
                && $fsrs->isMastered($next['stability'], $next['difficulty'], $next['interval_days']);

            DB::table('deck_items')->where('id', $card->id)->update([
                'stability'      => $next['stability'],
                'difficulty'     => $next['difficulty'],
                'interval_days'  => $next['interval_days'],
                'due_date'       => $next['due_date'],
                'elapsed_days'   => $elapsed,
                'reps'           => $d['rating'] === 1 ? 0 : DB::raw('reps + 1'),
                'lapses'         => $d['rating'] === 1 ? DB::raw('lapses + 1') : DB::raw('lapses'),
                'seen_count'     => DB::raw('seen_count + 1'),
                'again_count'    => DB::raw('again_count + ' . ($d['rating'] === 1 ? 1 : 0)),
                'mastered_at'    => $justMastered ? now()->toDateString() : $card->mastered_at,
                'last_review_at' => now(),
                'scheduler'      => 'fsrs6',
            ]);

            DB::table('review_logs')->insert([
                'user_id' => $uid, 'item_type' => $type, 'item_id' => $d['id'],
                'rating' => $d['rating'], 'mode' => $d['mode'] ?? 'deck',
                'interval_days' => $next['interval_days'],
                'ease_factor' => $card->ease_factor,          // برای سازگاری با گزارش‌های قدیمی
                'stability' => $next['stability'],
                'difficulty' => $next['difficulty'],
                'retrievability' => $before ? round($r, 4) : null,
                'elapsed_days' => $elapsed,
                'created_at' => now(),
            ]);

            $this->bumpActivity($uid, [
                'reviews' => 1,
                'reviews_ok' => $d['rating'] >= 3 ? 1 : 0,
                'mastered' => $justMastered ? 1 : 0,
            ]);

            return response()->json([
                'iv' => $next['interval_days'],
                'due' => $next['due_date'],
                's' => $next['stability'],
                'd' => $next['difficulty'],
                'mastered' => $justMastered,
                'preview' => $fsrs->preview($before, $elapsed),
            ]);
        });
    }

    /* =================================================================
     |  آزمون
     * ================================================================= */

    public function examStart(Request $req): JsonResponse
    {
        $d = $req->validate([
            'year' => 'required|integer|min:1380|max:1420',
            'exam' => 'required|in:ce,it,cs',
            'mode' => 'required|in:feedback,washback',
            'duration_sec' => 'nullable|integer|min:0|max:14400',
        ]);
        $uid = $req->user()->id;

        /* خریده، یا آزمونِ سالِ دمو */
        if (!$this->ent->canItem($uid, $d['exam'], (int) $d['year'])) {
            return response()->json(['error' => 'not_entitled', 'exam' => $d['exam'],
                                     'buy_url' => '/buy?exam=' . $d['exam']], 403);
        }

        // آزمون باز که مهلتش نگذشته → همان را برمی‌گردانیم، تازه نمی‌سازیم.
        $open = DB::table('exam_attempts')->where('user_id', $uid)->whereNull('finished_at')
            ->orderByDesc('id')->first();

        if ($open) {
            if ($open->deadline_at !== null && now()->gt($open->deadline_at)) {
                $this->grade($open->id, true);
            } else {
                return response()->json([
                    'id' => (int) $open->id, 'resumed' => true,
                    'year' => (int) $open->year, 'exam' => $open->exam, 'mode' => $open->mode,
                    'deadline_at' => $open->deadline_at,
                    'state' => json_decode($open->state_json ?: '{}', true),
                ]);
            }
        }

        $dur = (int) ($d['duration_sec'] ?? 0);
        $total = DB::table('questions')->where(['year' => $d['year'], 'exam' => $d['exam']])->count();

        if ($total === 0) {
            return response()->json(['error' => 'no_questions',
                'message' => 'سؤال‌های این سال هنوز وارد نشده است.'], 422);
        }

        $id = DB::table('exam_attempts')->insertGetId([
            'user_id' => $uid, 'year' => $d['year'], 'exam' => $d['exam'],
            'mode' => $d['mode'], 'duration_sec' => $dur,
            'started_at' => now(),
            'deadline_at' => $dur > 0 ? now()->addSeconds($dur) : null,
            'total_q' => $total, 'state_json' => '{}',
        ]);

        return response()->json([
            'id' => $id, 'resumed' => false, 'year' => $d['year'], 'exam' => $d['exam'],
            'mode' => $d['mode'],
            'deadline_at' => $dur > 0 ? now()->addSeconds($dur)->toIso8601String() : null,
            'state' => new \stdClass,
        ], 201);
    }

    public function examSave(Request $req, int $id): JsonResponse
    {
        $att = $this->ownAttempt($req, $id);
        if ($att->finished_at !== null) {
            return response()->json(['error' => 'finished'], 409);
        }
        $d = $req->validate(['state' => 'required|array']);

        // اگر مهلت گذشته، ذخیره نمی‌کنیم و همان‌جا می‌بندیم.
        if ($att->deadline_at !== null && now()->gt($att->deadline_at)) {
            $this->grade($id, true);
            /* پاسخ‌نامه فقط از report() — همان سقف روزانه (grade کلید خودش را بی‌سقف می‌ساخت) */
            return response()->json(['error' => 'deadline_passed', 'result' => $this->report($id)], 409);
        }

        DB::table('exam_attempts')->where('id', $id)
            ->update(['state_json' => json_encode($d['state'], JSON_UNESCAPED_UNICODE)]);

        return response()->json(['ok' => true]);
    }

    public function examFinish(Request $req, int $id): JsonResponse
    {
        $att = $this->ownAttempt($req, $id);
        if ($att->finished_at !== null) {
            return response()->json($this->report($id));
        }
        $auto = $att->deadline_at !== null && now()->gt($att->deadline_at);
        /* نمره با grade()؛ آنچه به کاربر می‌رود با report() — که سقف روزانه‌ی پاسخ را
           اعمال می‌کند. قبلاً خروجی خود grade برمی‌گشت و پایان اولِ آزمون سقف را دور می‌زد
           (تست test_exam_report_respects_daily_cap گرفتش). */
        $this->grade($id, $auto);
        return response()->json($this->report($id));
    }

    /** GET /api/exam/{id}/result — کارنامه‌ی یک آزمون تمام‌شده (برای باز کردن از تاریخچه). */
    public function examResult(Request $req, int $id): JsonResponse
    {
        $att = $this->ownAttempt($req, $id);
        if ($att->finished_at === null) {
            return response()->json(['error' => 'not_finished'], 409);
        }
        return response()->json($this->report($id));
    }

    public function examDrop(Request $req, int $id): JsonResponse
    {
        $att = $this->ownAttempt($req, $id);
        if ($att->finished_at !== null) return response()->json(['error' => 'finished'], 409);
        DB::table('exam_attempts')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    }

    private function ownAttempt(Request $req, int $id): object
    {
        $att = DB::table('exam_attempts')->where('id', $id)->first();
        if (!$att || (int) $att->user_id !== (int) $req->user()->id) {
            abort(404);
        }
        return $att;
    }

    /**
     * تصحیح — تنها جایی که نمره ساخته می‌شود.
     *
     * زمان را سرور می‌سنجد: اگر مهلت گذشته باشد، پاسخ‌هایی که بعد از مهلت
     * در state ذخیره شده‌اند حساب نمی‌شوند. زمان‌سنج مرورگر فقط نمایشی است.
     */
    public function grade(int $attemptId, bool $auto = false): array
    {
        return DB::transaction(function () use ($attemptId, $auto) {
            $att = DB::table('exam_attempts')->where('id', $attemptId)->lockForUpdate()->first();
            if (!$att || $att->finished_at !== null) return $this->report($attemptId);

            $state = json_decode($att->state_json ?: '{}', true) ?: [];
            $ans   = $state['ans'] ?? [];
            $bm    = $state['bm']  ?? [];
            $out   = $state['out'] ?? [];

            $qs = DB::table('questions')->where(['year' => $att->year, 'exam' => $att->exam])
                ->orderBy('question_number')
                ->get(['id', 'question_number', 'section', 'correct_option', 'explanation']);

            $rows = [];
            $key  = [];
            $r = $w = $b = 0;
            $bySection = [];

            foreach ($qs as $q) {
                // state با شماره‌ی تست کلید می‌خورد (همان چیزی که پروتوتایپ می‌نوشت).
                // مقدارش «اندیس گزینه» است که رابط از صفر می‌شمارد (گزینه‌ی ۱ = 0)،
                // ولی correct_option از یک. قبلاً بدون +1 مقایسه می‌شد: گزینه‌ی ۱
                // «نزده» حساب می‌شد و بقیه یکی جابه‌جا — نمره‌ی همه‌ی آزمون‌ها غلط بود.
                $raw    = $ans[(string) $q->question_number] ?? $ans[(string) $q->id] ?? null;
                $chosen = is_numeric($raw) ? (int) $raw + 1 : null;
                if ($chosen !== null && ($chosen < 1 || $chosen > 4)) $chosen = null;

                $ok = $chosen === null ? null : ($chosen === (int) $q->correct_option);

                $sec = ContentBuilder::SEC_FA[$q->section] ?? $q->section;
                if ($chosen === null)      { $b++; $bySection[$sec]['b'] = ($bySection[$sec]['b'] ?? 0) + 1; }
                elseif ($ok)               { $r++; $bySection[$sec]['r'] = ($bySection[$sec]['r'] ?? 0) + 1; }
                else                       { $w++; $bySection[$sec]['w'] = ($bySection[$sec]['w'] ?? 0) + 1; }

                $rows[] = [
                    'attempt_id' => $attemptId, 'question_id' => $q->id,
                    'chosen' => $chosen, 'is_correct' => $ok === null ? null : (int) $ok,
                    'flagged' => (int) !empty($bm[(string) $q->question_number]),
                    'eliminated' => (int) ($out[(string) $q->question_number] ?? 0),
                ];

                $key[] = [
                    'qid' => (int) $q->id, 'q' => (int) $q->question_number,
                    'correct' => (int) $q->correct_option, 'chosen' => $chosen,
                    'explanation' => $q->explanation,
                    'words' => $this->questionWords($q->id),
                ];
            }

            DB::table('exam_answers')->where('attempt_id', $attemptId)->delete();
            foreach (array_chunk($rows, 200) as $chunk) DB::table('exam_answers')->insert($chunk);

            $total   = count($qs);
            $percent = $total ? round((3 * $r - $w) / (3 * $total) * 100, 2) : 0;

            $started = strtotime($att->started_at);
            $endTs   = $auto && $att->deadline_at ? strtotime($att->deadline_at) : time();
            $used    = max(0, $endTs - $started);
            if ($att->duration_sec > 0) $used = min($used, (int) $att->duration_sec);

            DB::table('exam_attempts')->where('id', $attemptId)->update([
                'finished_at' => now(), 'auto_closed' => (int) $auto,
                'total_q' => $total, 'correct' => $r, 'wrong' => $w, 'blank' => $b,
                'percent' => $percent, 'used_sec' => $used,
            ]);

            $this->bumpActivity((int) $att->user_id, [
                'questions' => $r + $w, 'questions_ok' => $r,
            ]);

            foreach ($bySection as $s => &$v) {
                $v = ['r' => $v['r'] ?? 0, 'w' => $v['w'] ?? 0, 'b' => $v['b'] ?? 0];
            }
            unset($v);

            return [
                'correct' => $r, 'wrong' => $w, 'blank' => $b,
                'percent' => $percent, 'used_sec' => $used,
                'auto_closed' => $auto, 'bySection' => $bySection, 'key' => $key,
            ];
        });
    }

    private function report(int $attemptId): array
    {
        $att = DB::table('exam_attempts')->where('id', $attemptId)->first();
        $rows = DB::table('exam_answers as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->where('a.attempt_id', $attemptId)->orderBy('q.question_number')
            ->get(['q.id as qid', 'q.question_number as q', 'q.section', 'q.correct_option', 'q.explanation', 'a.chosen']);

        /* نتیجه به تفکیک بخش — قبلاً اینجا همیشه آرایه‌ی خالی برمی‌گشت و
           کارنامه‌ی بازشده از تاریخچه نمودار بخش‌ها را نداشت. */
        $bySection = [];
        foreach ($rows as $x) {
            $sec = ContentBuilder::SEC_FA[$x->section] ?? $x->section;
            $bySection[$sec] = $bySection[$sec] ?? ['r' => 0, 'w' => 0, 'b' => 0];
            $k = $x->chosen === null ? 'b' : ((int) $x->chosen === (int) $x->correct_option ? 'r' : 'w');
            $bySection[$sec][$k]++;
        }

        /* کارنامه هم پاسخ‌نامه است: همان سقف روزانه. بدون این، ساختن و بستن
           ۴۹ آزمون پشت‌سرهم کل پاسخ‌نامه را می‌داد. نمره‌ها همیشه هست؛ فقط
           پاسخ درست و تشریحیِ سؤال‌هایی که به سقف خوردند خالی می‌ماند. */
        $qa = $this->quota->allow((int) $att->user_id, $rows->pluck('qid')->all());
        $allowed = array_flip($qa['allowed']);
        if ($qa['fresh']) {
            DB::table('answer_reveals')->insert(array_map(fn ($qid) => [
                'user_id' => (int) $att->user_id, 'question_id' => $qid, 'reason' => 'finished',
                'attempt_id' => $attemptId, 'created_at' => now(),
            ], $qa['fresh']));
        }

        $key = $rows->map(fn ($x) => isset($allowed[(int) $x->qid]) ? [
                'qid' => (int) $x->qid, 'q' => (int) $x->q,
                'correct' => (int) $x->correct_option, 'chosen' => $x->chosen !== null ? (int) $x->chosen : null,
                'explanation' => $this->wm->mark((int) $att->user_id, $x->explanation),
                'words' => $this->questionWords((int) $x->qid),
            ] : [
                'qid' => (int) $x->qid, 'q' => (int) $x->q, 'correct' => null,
                'chosen' => $x->chosen !== null ? (int) $x->chosen : null,
                'explanation' => null, 'words' => [], 'limited' => true,
            ])->all();

        return [
            'limit_message' => $qa['limited'] ? $this->quota->message() : null,
            'correct' => (int) $att->correct, 'wrong' => (int) $att->wrong,
            'blank' => (int) $att->blank, 'percent' => (float) $att->percent,
            'used_sec' => (int) $att->used_sec, 'auto_closed' => (bool) $att->auto_closed,
            'bySection' => $bySection, 'key' => $key,
        ];
    }

    /* =================================================================
     |  فعالیت و رتبه‌بندی
     * ================================================================= */

    /**
     * POST /api/heartbeat
     * دو محافظ: فاصله‌ی حداقلی بین دو ضربان، و سقف روزانه.
     * هر دو اینجا اعمال می‌شوند چون throttle مسیر جلوی «هر ۲۶ ثانیه یک بار» را نمی‌گیرد.
     */
    public function heartbeat(Request $req): JsonResponse
    {
        $uid  = $req->user()->id;
        $day  = now()->toDateString();
        $last = cache()->get("zaban.hb.$uid");

        if ($last && (time() - $last) < self::HEARTBEAT_MIN_GAP) {
            return response()->json(['ok' => false, 'reason' => 'too_soon'], 429);
        }
        cache()->put("zaban.hb.$uid", time(), 120);

        $row = DB::table('daily_activity')->where(['user_id' => $uid, 'day' => $day])->first();
        $cur = (int) ($row->study_sec ?? 0);

        if ($cur >= self::DAILY_STUDY_CAP) {
            return response()->json(['ok' => true, 'capped' => true, 'study_sec' => $cur]);
        }

        $add = min(self::HEARTBEAT_STEP, self::DAILY_STUDY_CAP - $cur);
        $this->bumpActivity($uid, ['study_sec' => $add]);

        return response()->json(['ok' => true, 'study_sec' => $cur + $add]);
    }

    private function bumpActivity(int $uid, array $inc): void
    {
        $day = now()->toDateString();
        DB::table('daily_activity')->insertOrIgnore(['user_id' => $uid, 'day' => $day]);

        $sets = [];
        foreach ($inc as $col => $n) {
            if ($n > 0) $sets[$col] = DB::raw("$col + " . (int) $n);
        }
        if ($sets) {
            DB::table('daily_activity')->where(['user_id' => $uid, 'day' => $day])->update($sets);
        }
    }

    public function board(Request $req): JsonResponse
    {
        $days = (int) $req->query('days', 7);
        $map  = [1 => 'd1', 7 => 'd7', 30 => 'd30', 60 => 'd60', 90 => 'd90', 180 => 'd180', 365 => 'd365'];
        $period = $map[$days] ?? 'd7';
        $exam = $req->query('exam');

        /* leftJoin: کاربری که هنوز پروفایل نساخته هم در جدول هست (پیش‌فرض
           «نمایش در رتبه‌بندی» روشن است، همان که /me می‌گوید). */
        $q = DB::table('board_cache as b')
            ->leftJoin('zaban_profiles as p', 'p.user_id', '=', 'b.user_id')
            ->where('b.period', $period)->whereRaw('COALESCE(p.show_in_board, 1) = 1')
            ->when($exam, fn ($x) => $x->where('p.exam', $exam))
            ->orderByDesc('b.points');

        $rows = (clone $q)->limit(50)->get([
            'b.rank_no', 'b.user_id', 'p.nickname', 'p.exam', 'b.points', 'b.study_sec',
            'b.reviews', 'b.reviews_ok', 'b.questions', 'b.questions_ok', 'b.mastered',
        ])->map(fn ($r) => [
            /* me: رابط ردیف خود کاربر را با آمار زنده‌ی خودش نشان می‌دهد؛
               بدون این علامت کاربر دوبار در جدول دیده می‌شد. */
            'me' => (int) $r->user_id === (int) $req->user()->id,
            'rank' => (int) $r->rank_no, 'nick' => $r->nickname, 'exam' => $r->exam,
            'points' => (int) $r->points, 'study_sec' => (int) $r->study_sec,
            'reviews' => (int) $r->reviews, 'reviews_ok' => (int) $r->reviews_ok,
            'questions' => (int) $r->questions, 'questions_ok' => (int) $r->questions_ok,
            'mastered' => (int) $r->mastered,
        ]);

        $me = DB::table('board_cache')->where(['period' => $period, 'user_id' => $req->user()->id])->first();

        return response()->json([
            'users_total' => DB::table('board_cache')->where('period', $period)->count(),
            'rows' => $rows,
            'me' => $me ? [
                'rank' => (int) $me->rank_no, 'points' => (int) $me->points,
                'study_sec' => (int) $me->study_sec, 'reviews' => (int) $me->reviews,
                'questions' => (int) $me->questions, 'mastered' => (int) $me->mastered,
            ] : null,
            'built_at' => $me?->built_at,
        ]);
    }

    /* =================================================================
     |  کمکی
     * ================================================================= */

    /**
     * از یک فهرست شناسه، فقط آن‌هایی را برمی‌گرداند که کاربر رشته‌شان را دارد.
     *
     * بدون این، کاربری که فقط ce خریده می‌توانست کلمات it را در دک بریزد
     * و از همان‌جا معنی‌شان را ببیند — یعنی از در پشتی کل بانک را باز کند.
     */
    private function filterEntitledItems(int $uid, string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];

        /* رشته‌ی خریده‌شده، یا کاربردی در سالِ دمو (Entitlements::scopeQuery) */
        if ($type === 'question') {
            return $this->ent->scopeQuery(DB::table('questions')->whereIn('id', $ids), $uid)
                ->pluck('id')->map('intval')->all();
        }

        return $this->ent->scopeQuery(DB::table('word_occurrences')->whereIn('word_id', $ids), $uid)
            ->distinct()->pluck('word_id')->map('intval')->all();
    }

    /**
     * تاریخچه‌ی آزمون‌های کاربر.
     *
     * تا حالا فقط در مرورگر بود (کلید zban_hist) و با پاک شدن کش یا عوض
     * شدن مرورگر گم می‌شد، در حالی که همه‌ی آزمون‌ها در exam_attempts
     * نشسته‌اند. فقط آزمون‌های تمام‌شده؛ آزمون باز از مسیر /me می‌آید.
     *
     * شکل خروجی عمداً همان چیزی است که histAdd در مرورگر می‌ساخت.
     */
    public function examHistory(Request $req): JsonResponse
    {
        $rows = DB::table('exam_attempts')
            ->where('user_id', $req->user()->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->limit(200)
            ->get(['id','year','exam','mode','finished_at','used_sec',
                'total_q','correct','wrong','blank','percent','state_json']);

        return response()->json(['hist' => $rows->map(function ($r) {
            $state = json_decode($r->state_json ?: '{}', true) ?: [];

            /* ts هم برچسب تاریخ است هم شناسه‌ی ردیف در رابط (data-hist).
            ثانیه به تنهایی یکتا نیست، پس id را در سه رقم آخر می‌گذاریم. */
            $ts = strtotime($r->finished_at) * 1000 + ((int) $r->id % 1000);

            /* rate اگر تهی باشد رابط می‌شکند — بدون بررسی toFixed می‌زند. */
            $pct = $r->percent !== null ? (float) $r->percent : 0.0;

            return [
                'id'    => (int) $r->id,
                'ts'    => $ts,
                'y'     => (int) $r->year,
                'e'     => ContentBuilder::EXAM_FA[$r->exam] ?? $r->exam,
                'mode'  => $r->mode === 'washback' ? 'wb' : 'fb',
                'dur'   => (int) ($r->used_sec ?? 0),
                'n'     => (int) $r->total_q,
                'right' => (int) $r->correct,
                'wrong' => (int) $r->wrong,
                'blank' => (int) $r->blank,
                'pct'   => $pct,
                'ans'   => $state['ans'] ?? new \stdClass,
            ];
        })->all()]);
    }
}
