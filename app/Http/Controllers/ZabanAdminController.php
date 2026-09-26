<?php

namespace App\Http\Controllers;

use App\Services\ContentBuilder;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * پنل مدیریت پلتفرم زبان.
 *
 * عمداً جدا از رابط دانشجو ساخته شده: کاربر عادی هرگز آن را نمی‌بیند،
 * نیازهایش (جدول، فرم، فیلتر) با نیازهای رابط مطالعه فرق دارد، و بزرگ
 * کردن آن فایل HTML یک‌جای ۳۵۰ کیلوبایتی به نفع کسی نیست.
 *
 * دسترسی با میدل‌ور zaban.admin کنترل می‌شود که از users.type می‌خواند.
 */
class ZabanAdminController extends Controller
{
    /* ================= داشبورد ================= */

    public function dashboard(): View
    {
        return view('zaban-admin.dashboard', [
            'stats' => [
                'کاربران'        => DB::table('users')->count(),
                'دسترسی فعال'    => DB::table('zaban_entitlements')
                                        ->whereNull('revoked_at')
                                        ->where(fn ($q) => $q->whereNull('expires_at')
                                                             ->orWhere('expires_at', '>', now()))
                                        ->count(),
                'کلمات بانک'     => DB::table('words')->count(),
                'سؤال‌ها'        => DB::table('questions')->count(),
                'آزمون‌های داده‌شده' => DB::table('exam_attempts')->whereNotNull('finished_at')->count(),
                'سفارش‌های موفق'  => DB::table('zaban_orders')->where('status', 'paid')->count(),
            ],
            'gaps' => $this->contentGaps(),
        ]);
    }

    /**
     * دفترچه‌هایی که ناقص‌اند.
     *
     * مرجع، خود پلتفرم آزمون است نه exam_sections. دلیلش این است که
     * دفترچه‌های ناهمخوان اصلاً وارد نشده‌اند، پس در exam_sections هم
     * ردیفی ندارند و اگر از آنجا بپرسیم بی‌صدا از قلم می‌افتند —
     * یعنی پنل «همه کامل است» می‌گوید در حالی که نیست.
     *
     * تعداد مورد انتظار همان‌طور حساب می‌شود که SyncQuestions حساب
     * می‌کند: هر وکب یک سؤال، هر کلوز و پسیج به تعداد فرزندهایش.
     */
    private function contentGaps(): array
    {
        try {
            /* رشته از جدول واسط question_major (یک سؤال می‌تواند چند رشته داشته باشد) */
            $expected = DB::connection('azmoon')->table('question as q')
                ->join('question_major as qm', 'qm.question_id', '=', 'q.id')
                ->where('q.is_language', 1)->where('q.parent_id', 0)
                ->where('q.type', 'sarasari')->where('q.status', '!=', 'deleted')
                ->whereIn('qm.major_id', [1, 2, 3])
                ->selectRaw('q.year AS year, qm.major_id AS major_id,
                             SUM(CASE WHEN q.kind IN (2,3) THEN q.childes_count ELSE 1 END) AS n')
                ->groupBy('q.year', 'qm.major_id')
                ->get();
        } catch (\Throwable $e) {
            /* دیتابیس آزمون در دسترس نیست — بهتر است پنل بگوید نمی‌داند
               تا اینکه «همه کامل است» را جای واقعیت جا بزند. */
            return ['error' => 'اتصال به دیتابیس پلتفرم آزمون برقرار نشد.'];
        }

        $major = [1 => 'ce', 2 => 'it', 3 => 'cs'];

        $actual = DB::table('questions')
            ->selectRaw('year, exam, COUNT(*) AS n')
            ->groupBy('year', 'exam')->get()
            ->keyBy(fn ($r) => $r->year . '|' . $r->exam);

        $gaps = [];
        foreach ($expected as $e) {
            $code = $major[$e->major_id] ?? null;
            if (!$code) continue;

            $have = (int) ($actual[$e->year . '|' . $code]->n ?? 0);
            $want = (int) $e->n;
            if ($have >= $want) continue;

            $gaps[] = [
                'year'     => (int) $e->year,
                'exam'     => Pricing::NAMES[$code] ?? $code,
                'missing'  => $want - $have,
                'expected' => $want,
                'actual'   => $have,
            ];
        }

        usort($gaps, fn ($a, $b) => $b['year'] <=> $a['year']);
        return $gaps;
    }

    /* ================= تنظیمات ================= */

    public function settings(Pricing $pricing, \App\Services\Entitlements $ent): View
    {
        /* همه‌ی سال‌هایی که دفترچه یا سؤال دارند (نه فقط سؤال — سالی که هنوز
           سؤالش وارد نشده هم باید دیده شود)، با تعداد سؤال هر رشته، تا مدیر
           دفترچه‌ی ناقص را ندانسته دمو نکند. */
        $counts = DB::table('questions')->selectRaw('year, exam, COUNT(*) AS n')
            ->groupBy('year', 'exam')->get()
            ->groupBy('year')->map(fn ($g) => $g->pluck('n', 'exam')->all());
        $qCount = DB::table('exam_sections')->distinct()->pluck('year')
            ->merge(DB::table('word_occurrences')->distinct()->pluck('year'))
            ->merge($counts->keys())
            ->map(fn ($y) => (int) $y)->unique()->sortDesc()->values()
            ->mapWithKeys(fn ($y) => [$y => $counts[$y] ?? []]);

        return view('zaban-admin.settings', [
            'bundles'   => $pricing->bundles(),
            'version'   => $pricing->version(),
            /* نمایش به شمسی؛ ذخیره میلادی */
            'examDate'  => \App\Support\Jalali::formatFromGregorian($pricing->accessUntil()),
            'demoYear'  => $ent->demoYear(),
            'sec'       => app(\App\Services\Security\Settings::class)->all(),
            'newPerDay' => app(\App\Services\Security\Settings::class)->newPerDay(),
            'secFields' => \App\Services\Security\Settings::FIELDS,
            'demoExams' => $ent->demoExamsSetting(),
            'qCount'    => $qCount,
        ]);
    }

    public function saveSettings(Request $req, Pricing $pricing, \App\Services\Entitlements $ent): RedirectResponse
    {
        $data = $req->validate([
            'demo_year'    => ['nullable', 'integer', 'min:1380', 'max:1420'],
            'sec'          => ['nullable', 'array'],
            'sec.*'        => ['nullable', 'integer'],     /* کمینه/بیشینه را Settings::save اعمال می‌کند */
            'new_per_day'  => ['nullable', 'integer'],
            'demo_exams'   => ['nullable', 'array'],
            'demo_exams.*' => ['in:ce,it,cs'],
            'p1' => ['required', 'integer', 'min:0', 'max:100000000'],
            'p2' => ['required', 'integer', 'min:0', 'max:100000000'],
            'p3' => ['required', 'integer', 'min:0', 'max:100000000'],
            'exam_date' => ['nullable', 'string', 'max:30'],
        ], [], [
            'p1' => 'قیمت یک رشته', 'p2' => 'قیمت دو رشته', 'p3' => 'قیمت سه رشته',
        ]);

        $pricing->saveBundles([1 => $data['p1'], 2 => $data['p2'], 3 => $data['p3']]);

        $ent->saveDemo(isset($data['demo_year']) ? (int) $data['demo_year'] : null, $data['demo_exams'] ?? []);

        /* امنیت محتوا — هر عدد بین کمینه و بیشینه‌ی خودش نگه داشته می‌شود (Settings::FIELDS) */
        app(\App\Services\Security\Settings::class)->save(array_filter($data['sec'] ?? [], fn ($v) => $v !== null),
                                                          $req->boolean('sec_list_meanings'));
        if (isset($data['new_per_day'])) {
            app(\App\Services\Security\Settings::class)->saveNewPerDay((int) $data['new_per_day']);
        }

        if ($req->filled('exam_date')) {
            /* مدیر شمسی می‌نویسد؛ میلادی ذخیره می‌شود. expires_at دسترسی خریدارها
               همین است — شمسیِ خام را پایگاه داده سال ۱۴۰۶ میلادی می‌خواند. */
            $g = \App\Support\Jalali::parseToGregorian($data['exam_date']);
            if (!$g) {
                return back()->withInput()->withErrors(['exam_date' =>
                    'تاریخ کنکور درست نیست. نمونه: ۱۴۰۶/۰۲/۱۶ یا 1406-02-16 08:00']);
            }
            if ($g <= now()->toDateTimeString()) {
                return back()->withInput()->withErrors(['exam_date' =>
                    'تاریخ کنکور گذشته است؛ خریدهای تازه بلافاصله منقضی می‌شدند.']);
            }
            DB::table('zaban_meta')->updateOrInsert(['k' => 'exam_date'], ['v' => $g, 'updated_at' => now()]);
        }

        return back()->with('ok', 'تنظیمات ذخیره شد.');
    }

    /* ================= کاربران ================= */

    public function users(Request $req): View
    {
        $q = trim((string) $req->query('q', ''));

        $users = DB::table('users as u')
            ->leftJoin('zaban_profiles as p', 'p.user_id', '=', 'u.id')
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('u.name', 'like', "%$q%")
                  ->orWhere('u.email', 'like', "%$q%")
                  ->orWhere('p.nickname', 'like', "%$q%");
            }))
            ->orderByDesc('u.id')
            ->limit(100)
            ->get(['u.id', 'u.name', 'u.email', 'u.type', 'p.nickname', 'p.exam']);

        $ent = DB::table('zaban_entitlements')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereNull('revoked_at')
            ->get(['user_id', 'exam'])
            ->groupBy('user_id')
            ->map(fn ($g) => $g->pluck('exam')->all());

        return view('zaban-admin.users', compact('users', 'ent', 'q'));
    }

    /* ================= محتوا ================= */

    public function content(ContentBuilder $cb): View
    {
        $years = DB::table('word_occurrences')
            ->selectRaw('year, exam, COUNT(*) AS occ')
            ->groupBy('year', 'exam')->orderByDesc('year')->get();

        return view('zaban-admin.content', [
            'years'    => $years,
            'versions' => DB::table('zaban_meta')->where('k', 'like', 'content_version_%')
                            ->pluck('v', 'k'),
        ]);
    }

    /** بالا بردن نسخه‌ی محتوا — مرورگرها دفعه‌ی بعد تازه می‌گیرند. */
    public function bump(ContentBuilder $cb): RedirectResponse
    {
        $cb->bump();
        return back()->with('ok', 'نسخه‌ی محتوا بالا رفت. مرورگرها دفعه‌ی بعد تازه می‌گیرند.');
    }
}
