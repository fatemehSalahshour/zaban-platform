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
     * تعداد مورد انتظار دقیقاً همان‌طور شمرده می‌شود که SyncQuestions
     * می‌شمارد: وکب یک سؤال، کلوز و پسیج به تعداد فرزندهای زنده‌شان.
     *
     * قبلاً اینجا از ستون childes_count استفاده می‌شد و سینک از شمردن
     * واقعی فرزندها. آن ستون یک شمارنده‌ی ذخیره‌شده است و سؤالِ حذف‌شده
     * را هم می‌شمارد، پس پنل عددی بزرگ‌تر از واقعیت نشان می‌داد و
     * دفترچه‌های کامل را «ناقص» جا می‌زد — تا جایی که برای ۱۳۹۲ عدد ۳۱
     * می‌داد، که اصلاً ممکن نیست.
     *
     * سؤال‌های والد هم با زیرکوئری گرفته می‌شوند نه join، چون سؤالی که
     * بین دو رشته مشترک است در join دو بار برمی‌گردد و عدد را باد می‌کند
     * (همان دلیلی که در SyncQuestions::plan() نوشته شده).
     */
    private function contentGaps(): array
    {
        $major = [1 => 'ce', 2 => 'it', 3 => 'cs'];
        $expected = [];

        try {
            foreach ($major as $mid => $code) {
                $parents = DB::connection('azmoon')->table('question')
                    ->where('is_language', 1)->where('parent_id', 0)
                    ->where('type', 'sarasari')->where('status', '!=', 'deleted')
                    ->whereIn('id', fn ($sub) => $sub->select('question_id')
                        ->from('question_major')->where('major_id', $mid))
                    ->get(['id', 'year', 'kind']);

                if ($parents->isEmpty()) continue;

                /* فرزندهای زنده‌ی همه‌ی والدها در یک کوئری، نه یکی‌یکی */
                $kids = DB::connection('azmoon')->table('question')
                    ->whereIn('parent_id', $parents->pluck('id'))
                    ->where('status', '!=', 'deleted')
                    ->selectRaw('parent_id, COUNT(*) AS n')
                    ->groupBy('parent_id')->pluck('n', 'parent_id');

                foreach ($parents as $p) {
                    /* kind: ۱ وکب، ۲ پسیج، ۳ کلوز — همان نگاشت SyncQuestions */
                    if (!in_array((int) $p->kind, [1, 2, 3], true)) continue;

                    $n = (int) $p->kind === 1 ? 1 : (int) ($kids[$p->id] ?? 0);
                    if ($n === 0) continue;          /* والد بی‌فرزند: سینک هم ردش می‌کند */

                    $expected[$p->year . '|' . $code] = ($expected[$p->year . '|' . $code] ?? 0) + $n;
                }
            }
        } catch (\Throwable $e) {
            /* دیتابیس آزمون در دسترس نیست — بهتر است پنل بگوید نمی‌داند
               تا اینکه «همه کامل است» را جای واقعیت جا بزند. */
            return ['error' => 'اتصال به دیتابیس پلتفرم آزمون برقرار نشد.'];
        }

        $actual = DB::table('questions')
            ->selectRaw('year, exam, COUNT(*) AS n')
            ->groupBy('year', 'exam')->get()
            ->keyBy(fn ($r) => $r->year . '|' . $r->exam);

        $gaps = [];
        foreach ($expected as $key => $want) {
            [$year, $code] = explode('|', $key);
            $have = (int) ($actual[$key]->n ?? 0);
            if ($have >= $want) continue;

            $gaps[] = [
                'year'     => (int) $year,
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
            'launch'    => $pricing->launchOffer(),
            'version'   => $pricing->version(),
            /* نمایش به شمسی؛ ذخیره میلادی */
            'examDate'  => \App\Support\Jalali::formatFromGregorian($pricing->accessUntil()),
            'trialOn'   => $ent->trialOn(),
            'trialCap'  => $ent->trialCap(),
            'trialBooks'=> $ent->trialBooklets(),
            'sec'       => app(\App\Services\Security\Settings::class)->all(),
            'newPerDay' => app(\App\Services\Security\Settings::class)->newPerDay(),
            'revPerDay' => app(\App\Services\Security\Settings::class)->revPerDay(),
            'secFields' => \App\Services\Security\Settings::FIELDS,

            'qCount'    => $qCount,
        ]);
    }

    public function saveSettings(Request $req, Pricing $pricing, \App\Services\Entitlements $ent): RedirectResponse
    {
        $data = $req->validate([
            /* نسخه‌ی آزمایشی: سقف کلمه و دفترچه‌هایی که آزمون کاملشان باز است */
            'trial_on'        => ['nullable'],
            'trial_cap'       => ['nullable', 'integer', 'min:0', 'max:5000'],
            'trial_books'     => ['nullable', 'array', 'max:6'],
            'trial_books.*'   => ['nullable', 'string', 'max:12'],
            'sec'          => ['nullable', 'array'],
            'sec.*'        => ['nullable', 'integer'],     /* کمینه/بیشینه را Settings::save اعمال می‌کند */
            'new_per_day'  => ['nullable', 'integer'],
            'rev_per_day'  => ['nullable', 'integer'],
            'p1' => ['required', 'integer', 'min:0', 'max:100000000'],
            'p2' => ['required', 'integer', 'min:0', 'max:100000000'],
            'p3' => ['required', 'integer', 'min:0', 'max:100000000'],
            'exam_date' => ['nullable', 'string', 'max:30'],
            /* تخفیف رونمایی — درصد و بازه‌ی شمسی */
            'launch_off'      => ['nullable', 'integer', 'min:0', 'max:90'],
            'launch_off_from' => ['nullable', 'string', 'max:30'],
            'launch_off_to'   => ['nullable', 'string', 'max:30'],
            /* متن تخفیف — روی صفحه‌ی خرید و لندینگ همین نوشته می‌شود */
            'launch_off_title' => ['nullable', 'string', 'max:40'],
            'launch_off_note'  => ['nullable', 'string', 'max:120'],
        ], [], [
            'p1' => 'قیمت یک رشته', 'p2' => 'قیمت دو رشته', 'p3' => 'قیمت سه رشته',
            'launch_off' => 'درصد تخفیف رونمایی',
        ]);

        $pricing->saveBundles([1 => $data['p1'], 2 => $data['p2'], 3 => $data['p3']]);

        /* تخفیف رونمایی. تاریخ‌ها مثل exam_date شمسی نوشته و میلادی ذخیره
           می‌شوند؛ اگر خام بمانند، مقایسه‌ی بازه سال ۱۴۰۶ میلادی می‌شود و
           تخفیف هیچ‌وقت فعال نمی‌شود. «تا» به پایان همان روز کشیده می‌شود
           وگرنه روز آخر از نیمه‌شب می‌پرد. */
        $jal = function (?string $v, bool $endOfDay = false): ?string {
            $v = trim((string) $v);
            if ($v === '') return null;
            $g = \App\Support\Jalali::parseToGregorian($v) ?: null;
            if (!$g) return null;
            return $endOfDay && !preg_match('~\d:\d~', $g)
                ? substr($g, 0, 10) . ' 23:59:59' : $g;
        };
        $pricing->saveLaunchOffer(
            (int) ($data['launch_off'] ?? 0),
            $jal($data['launch_off_from'] ?? null),
            $jal($data['launch_off_to'] ?? null, true),
            $data['launch_off_title'] ?? null,
            $data['launch_off_note'] ?? null,
        );

        /* «1404:ce» → ['year' => 1404, 'exam' => 'ce'] */
        $books = [];
        foreach ($data['trial_books'] ?? [] as $pair) {
            [$y, $e] = array_pad(explode(':', (string) $pair), 2, null);
            if (is_numeric($y) && in_array($e, ['ce', 'it', 'cs'], true)) {
                $books[] = ['year' => (int) $y, 'exam' => $e];
            }
        }
        $ent->saveTrial($req->boolean('trial_on'), (int) ($data['trial_cap'] ?? 200), $books);

        /* امنیت محتوا — هر عدد بین کمینه و بیشینه‌ی خودش نگه داشته می‌شود (Settings::FIELDS) */
        app(\App\Services\Security\Settings::class)->save(array_filter($data['sec'] ?? [], fn ($v) => $v !== null),
                                                          $req->boolean('sec_list_meanings'));
        if (isset($data['new_per_day'])) {
            app(\App\Services\Security\Settings::class)->saveNewPerDay((int) $data['new_per_day']);
        }
        if (isset($data['rev_per_day'])) {
            app(\App\Services\Security\Settings::class)->saveRevPerDay((int) $data['rev_per_day']);
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
