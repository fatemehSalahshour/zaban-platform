<?php

namespace App\Http\Controllers;

use App\Services\ContentBuilder;
use App\Services\Entitlements;
use App\Services\Payment\Checkout;
use App\Services\Pricing;
use Illuminate\Validation\Rule;
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
            /* محافظ محتوا: پنهان شدن صفحه هنگام خروج از پنجره (protect.js) — پیش‌فرض روشن */
            'guardHide' => DB::table('zaban_meta')->where('k', 'guard_hide_on_blur')->value('v') !== '0',

            /* درگاه پرداخت: آمادگی فنی (.env) + انتخاب مدیر */
            'gwReady'   => app(\App\Services\Payment\Gateways::class)->readiness(),
            'gwPrefs'   => app(\App\Services\Payment\Gateways::class)->prefs(),

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
            /* درگاه‌های روشن در صفحه‌ی خرید */
            'gateways'        => ['nullable', 'array'],
            'gateways.*'      => ['in:' . implode(',', \App\Services\Payment\Gateways::SELECTABLE)],
            'gateway_default' => ['nullable', 'in:' . implode(',', \App\Services\Payment\Gateways::SELECTABLE)],
        ], [], [
            'p1' => 'قیمت یک رشته', 'p2' => 'قیمت دو رشته', 'p3' => 'قیمت سه رشته',
            'launch_off' => 'درصد تخفیف رونمایی',
        ]);

        /* درگاه‌ها — پیش از هر ذخیره‌ی دیگر بررسی می‌شود تا با خطا چیزی نیمه‌کاره ثبت نشود.
           دست‌کم یک درگاه «آماده» باید روشن بماند، وگرنه هیچ‌کس نمی‌تواند بخرد. */
        $gws = app(\App\Services\Payment\Gateways::class);
        if ($req->boolean('gateways_form')) {
            $on = array_values(array_unique($data['gateways'] ?? []));
            $ready = array_keys(array_filter($gws->readiness(), fn ($g) => $g['ready']));
            if (!array_intersect($on, $ready)) {
                return back()->withInput()->withErrors(['gateways' => $ready
                    ? 'دست‌کم یک درگاه آماده باید روشن باشد؛ وگرنه هیچ دانشجویی نمی‌تواند پرداخت کند.'
                    : 'هیچ درگاهی در .env تنظیم نشده است.']);
            }
        }

        $pricing->saveBundles([1 => $data['p1'], 2 => $data['p2'], 3 => $data['p3']]);

        if ($req->boolean('gateways_form')) {
            $gws->savePrefs($on, $data['gateway_default'] ?? null);
        }

        /* تخفیف رونمایی. تاریخ‌ها مثل exam_date شمسی نوشته و میلادی ذخیره
           می‌شوند؛ اگر خام بمانند، مقایسه‌ی بازه سال ۱۴۰۶ میلادی می‌شود و
           تخفیف هیچ‌وقت فعال نمی‌شود. «تا» به پایان همان روز کشیده می‌شود
           وگرنه روز آخر از نیمه‌شب می‌پرد. */
        $jal = function (?string $v, bool $endOfDay = false): ?string {
            $v = trim((string) $v);
            if ($v === '') return null;
            $g = \App\Support\Jalali::parseToGregorian($v) ?: null;
            if (!$g) return null;
            /* تاریخِ بدون ساعت: برای «از» ابتدای روز، برای «تا» پایان روز.
               قبلاً هر دو پایان روز می‌شدند، پس تخفیفی که شروعش امروز بود تا
               نیمه‌شب روشن نمی‌شد و مدیر مجبور بود یک روز عقب‌تر بنویسد. */
            $hasTime = (bool) preg_match('~\d{1,2}:\d{2}~', $v);
            if ($hasTime) return $g;
            return substr($g, 0, 10) . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
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
        if ($req->has('guard_form')) {
            DB::table('zaban_meta')->updateOrInsert(['k' => 'guard_hide_on_blur'],
                ['v' => $req->boolean('guard_hide') ? '1' : '0', 'updated_at' => now()]);
        }
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
        /* جست‌وجوی موبایل: کاربر را با شماره می‌شناسیم (ورود با همان است) و
           رقم فارسی هم باید بگیرد، چون مدیر معمولاً از جایی کپی می‌کند. */
        $digits = strtr($q, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                             '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);

        $users = DB::table('users as u')
            ->leftJoin('zaban_profiles as p', 'p.user_id', '=', 'u.id')
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q, $digits) {
                $w->where('u.name', 'like', "%$q%")
                  ->orWhere('u.email', 'like', "%$q%")
                  ->orWhere('p.nickname', 'like', "%$q%")
                  ->orWhere('u.mobile', 'like', "%$digits%")
                  ->orWhere('p.university', 'like', "%$q%");
                if (ctype_digit($digits)) $w->orWhere('u.id', (int) $digits);
            }))
            ->orderByDesc('u.id')
            ->limit(100)
            ->get(['u.id', 'u.name', 'u.email', 'u.mobile', 'u.type', 'p.nickname', 'p.exam']);

        $ent = DB::table('zaban_entitlements')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereNull('revoked_at')
            ->get(['user_id', 'exam'])
            ->groupBy('user_id')
            ->map(fn ($g) => $g->pluck('exam')->all());

        return view('zaban-admin.users', compact('users', 'ent', 'q'));
    }

    /* ==================== سفارش‌ها ====================
       بدون این صفحه، هر سفارش گیرکرده یعنی رفتن سراغ خط فرمان. و چون
       پای پول کاربر وسط است، آن مسیر هم کند است هم ترسناک. */

    /** GET /zaban-admin/orders */
    public function orders(Request $req): View
    {
        $state = $req->query('state', 'stuck');
        $q     = trim((string) $req->query('q', ''));
        $digits = strtr($q, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                             '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);

        $orders = DB::table('zaban_orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->when($state === 'stuck',  fn ($x) => $x->whereIn('o.status', ['pending', 'verifying']))
            ->when($state === 'paid',   fn ($x) => $x->where('o.status', 'paid'))
            ->when($state === 'failed', fn ($x) => $x->whereIn('o.status', ['failed', 'expired']))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q, $digits) {
                $w->where('u.mobile', 'like', "%$digits%")->orWhere('u.name', 'like', "%$q%");
                if (ctype_digit($digits)) {
                    $w->orWhere('o.id', (int) $digits)
                      /* کد پیگیری که کاربر از رسید درگاه می‌فرستد */
                      ->orWhere('o.ref_id', $digits)->orWhere('o.rrn', $digits);
                }
                /* شناسه‌ی تراکنش زرین‌پال (Authority) یا نشانه‌ی ایران کیش */
                if (preg_match('/^[A-Za-z0-9]{20,64}$/', $q)) $w->orWhere('o.token', $q);
            }))
            ->orderByDesc('o.id')->limit(150)
            ->get(['o.id', 'o.user_id', 'o.exams', 'o.payable', 'o.status', 'o.gateway',
                   'o.gateway_code', 'o.token', 'o.rrn', 'o.ref_id', 'o.amount_rial',
                   'o.created_at', 'o.paid_at',
                   'u.name', 'u.mobile']);

        return view('zaban-admin.orders', [
            'orders' => $orders,
            'state'  => $state,
            'q'      => $q,
            'counts' => DB::table('zaban_orders')
                ->selectRaw("SUM(status IN ('pending','verifying')) AS stuck,
                             SUM(status = 'paid') AS paid,
                             SUM(status IN ('failed','expired')) AS failed")
                ->first(),
        ]);
    }

    /** GET /zaban-admin/secreport — آخرین گزارش امنیتی شبانه */
    public function secReport(): View
    {
        $raw = DB::table('zaban_meta')->where('k', 'secure_report')->value('v');
        return view('zaban-admin.secreport', ['r' => $raw ? json_decode($raw, true) : null]);
    }

    /** POST /zaban-admin/secreport/run — ساختن گزارش همین حالا */
    public function secReportRun(): RedirectResponse
    {
        \Illuminate\Support\Facades\Artisan::call('zaban:secure-report', ['--quiet-output' => true]);
        return redirect()->route('zadmin.secreport')->with('ok', 'گزارش تازه ساخته شد.');
    }

    /** POST /zaban-admin/orders/{id}/recheck — استعلام دوباره از درگاه. */
    public function orderRecheck(int $id, Checkout $checkout): RedirectResponse
    {
        $o = DB::table('zaban_orders')->where('id', $id)->first();
        if (!$o) return back()->with('error', 'سفارش پیدا نشد.');
        if ($o->status === 'paid') return back()->with('ok', 'این سفارش از قبل پرداخت‌شده است.');

        try {
            $r = $checkout->reconcile($o);
        } catch (\Throwable $e) {
            return back()->with('error', 'استعلام انجام نشد: ' . $e->getMessage());
        }

        /* خروجی خام reconcile به فارسی، وگرنه مدیر باید حدس بزند */
        return back()->with('ok', match ($r) {
            'paid-by-inquiry' => 'درگاه پرداخت را تأیید کرد؛ دسترسی باز شد.',
            'retried-confirm' => 'تأییدیه دوباره فرستاده شد؛ چند لحظه بعد دوباره بررسی کنید.',
            'closed'          => 'درگاه می‌گوید پرداخت نشده؛ سفارش بسته شد.',
            'inquiry-error'   => 'درگاه جواب نداد. بعداً دوباره امتحان کنید.',
            'waiting'         => 'هنوز زود است — سفارش تازه است و باید چند دقیقه بگذرد.',
            default           => 'بررسی شد: ' . $r,
        });
    }

    /** POST /zaban-admin/orders/{id}/activate — فعال‌سازی دستی (کارت به کارت). */
    public function orderActivate(Request $req, int $id, Checkout $checkout): RedirectResponse
    {
        $d = $req->validate(
            ['note' => ['required', 'string', 'min:4', 'max:200']],
            [], ['note' => 'توضیح پرداخت']
        );

        try {
            $checkout->activateManually($id, $d['note'], $req->user()->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'فعال نشد: ' . $e->getMessage());
        }
        return back()->with('ok', 'سفارش دستی فعال شد و دسترسی باز شد.');
    }

    /** نقش‌هایی که مدیر می‌تواند بدهد. ترتیب از کم‌دسترسی به پردسترسی. */
    public const ROLES = [
        'student' => 'دانشجو',
        'editor'  => 'ویراستار محتوا',
        'manager' => 'مدیر محتوا',
        'admin'   => 'مدیر کل',
    ];

    /** GET /zaban-admin/users/{id} — پرونده‌ی یک کاربر. */
    public function user(int $id, Pricing $pricing): View
    {
        $u = DB::table('users as u')
            ->leftJoin('zaban_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('u.id', $id)
            ->first(['u.id', 'u.name', 'u.email', 'u.mobile', 'u.type', 'u.created_at',
                     'p.nickname', 'p.exam', 'p.university', 'p.gpa', 'p.quota',
                     'p.degree', 'p.show_in_board', 'p.new_per_day', 'p.rev_per_day']);
        abort_if(!$u, 404);

        return view('zaban-admin.user', [
            'u'     => $u,
            'roles' => self::ROLES,
            'ents'  => DB::table('zaban_entitlements')->where('user_id', $id)
                         ->get()->keyBy('exam'),
            'until' => $pricing->accessUntil(),
            'orders' => DB::table('zaban_orders')->where('user_id', $id)
                          ->orderByDesc('id')->limit(10)
                          ->get(['id', 'exams', 'payable', 'status', 'gateway', 'gateway_code',
                                 'ref_id', 'rrn', 'paid_at', 'created_at']),
        ]);
    }

    /** PUT /zaban-admin/users/{id} — ذخیره‌ی اطلاعات، نقش و دسترسی‌ها. */
    public function userSave(Request $req, int $id, Pricing $pricing, Entitlements $ent): RedirectResponse
    {
        $u = DB::table('users')->where('id', $id)->first(['id', 'type']);
        abort_if(!$u, 404);

        $d = $req->validate([
            'name'       => ['required', 'string', 'min:2', 'max:60', 'regex:/^[^<>"&]*$/u'],
            'nickname'   => ['nullable', 'string', 'max:30', 'regex:/^[^<>"&]*$/u'],
            'university' => ['nullable', 'string', 'max:60'],
            'gpa'        => ['nullable', 'numeric', 'between:0,20'],
            'quota'      => ['nullable', 'string', 'max:30'],
            'exam'       => ['nullable', Rule::in(Entitlements::EXAMS)],
            'type'       => ['required', Rule::in(array_keys(self::ROLES))],
            'exams'      => ['array'],
            'exams.*'    => [Rule::in(Entitlements::EXAMS)],
        ], [], ['name' => 'نام و نام خانوادگی', 'gpa' => 'معدل', 'type' => 'سطح کاربری']);

        /* آخرین مدیر کل را نمی‌شود پایین آورد، وگرنه در پنل قفل می‌شویم. */
        if ($u->type === 'admin' && $d['type'] !== 'admin'
            && DB::table('users')->where('type', 'admin')->count() <= 1) {
            return back()->with('error', 'این تنها مدیر کل سامانه است؛ اول یک مدیر دیگر بسازید.');
        }

        DB::table('users')->where('id', $id)->update([
            'name' => trim($d['name']), 'type' => $d['type'], 'updated_at' => now(),
        ]);

        $prof = ['nickname' => $d['nickname'] ?: null, 'university' => $d['university'] ?: null,
                 'gpa' => $d['gpa'] !== null && $d['gpa'] !== '' ? $d['gpa'] : null,
                 'quota' => $d['quota'] ?: null, 'updated_at' => now()];
        if (!empty($d['exam'])) $prof['exam'] = $d['exam'];

        if (DB::table('zaban_profiles')->where('user_id', $id)->exists()) {
            DB::table('zaban_profiles')->where('user_id', $id)->update($prof);
        } else {
            DB::table('zaban_profiles')->insert($prof + [
                'user_id' => $id, 'exam' => $d['exam'] ?? 'ce',
                'show_in_board' => 0, 'created_at' => now(),
            ]);
        }

        /* دسترسی رشته‌ها: هر تیک‌خورده grant، هر تیک‌نخورده revoke.
           grant idempotent است، پس تیک‌های قبلی دست‌نخورده می‌مانند و انقضا
           عقب نمی‌رود. */
        $want = $d['exams'] ?? [];
        $by   = 'از پنل مدیریت توسط ' . (auth()->user()->name ?? '—');

        try {
            foreach (Entitlements::EXAMS as $exam) {
                $has = DB::table('zaban_entitlements')->where(['user_id' => $id, 'exam' => $exam])
                         ->whereNull('revoked_at')->exists();
                if (in_array($exam, $want, true) && !$has) {
                    /* 'staff' تنها مقدار مجاز برای اعطای دستی است؛ ستون source
                       بیش از purchase و staff نمی‌پذیرد و هر چیز دیگری خطای
                       دیتابیس می‌دهد. */
                    $ent->grant($id, $exam, $pricing->accessUntil(), 'staff', null, $by);
                } elseif (!in_array($exam, $want, true) && $has) {
                    $ent->revoke($id, $exam, $by);
                }
            }
        } catch (\Throwable $e) {
            /* خطای ۵۰۰ در این صفحه یعنی مدیر نمی‌فهمد دسترسی داده شد یا نه،
               در حالی که کاربر پولش را داده. پس علت را می‌گوییم. */
            \Illuminate\Support\Facades\Log::error('[Admin] grant failed', [
                'user' => $id, 'want' => $want, 'err' => $e->getMessage(),
            ]);
            return back()->with('error', 'دسترسی ثبت نشد: ' . $e->getMessage());
        }

        $ent->forget($id);

        return redirect()->route('zadmin.user', $id)->with('ok', 'ذخیره شد.');
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
