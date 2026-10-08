<?php

namespace App\Http\Controllers;

use App\Services\Entitlements;
use App\Services\Payment\Checkout;
use App\Services\Payment\Gateways;
use App\Services\Payment\IranKish;
use App\Services\Payment\Zarinpal;
use App\Services\Pricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خرید پکیج — صفحه‌ی مستقل /buy (نه داخل رابط تک‌صفحه‌ای، چون کاربری که
 * هیچ رشته‌ای نخریده رابط را اصلاً نمی‌تواند بارگذاری کند).
 * منطق پرداخت در App\Services\Payment\Checkout است.
 *
 * مسیر قدیمی /api/buy/callback (با $verified = true) حذف شد.
 *
 * دو درگاه: ایران کیش (برگشت POST به /buy/return) و زرین‌پال (برگشت GET به
 * /buy/zarinpal/return). کاربر در صفحه‌ی خرید انتخاب می‌کند؛ اگر فقط یکی روشن
 * باشد انتخابی نشان داده نمی‌شود. روشن/خاموش از .env — config/gateways.php.
 */
class ZabanPurchaseController extends Controller
{
    public function __construct(
        private Pricing $pricing,
        private Entitlements $ent,
        private Checkout $checkout,
        private IranKish $gateway,
        private Gateways $gateways,
    ) {}

    /** GET /api/buy/quote?exams=ce,it — خلاصه‌ی زنده‌ی سفارش */
    public function quote(Request $req): JsonResponse
    {
        $exams = array_filter(explode(',', (string) $req->query('exams', '')));
        $owned = $req->user() ? $this->ent->for($req->user()->id) : [];

        return response()->json(
            $this->pricing->quote($exams, $owned) + ['access_until' => $this->pricing->accessUntil()]
        );
    }

    /** GET /buy — صفحه‌ی انتخاب رشته‌ها */
    public function page(Request $req)
    {
        $owned = $this->ent->for($req->user()->id);

        /* پیش‌فرض: هر رشته‌ای که هنوز ندارد تیک خورده باشد.
           قبلاً صفحه با انتخاب خالی باز می‌شد و جمع صفر بود، پس کاربری که از
           لندینگ می‌آمد اول باید خودش دنبال تیک می‌گشت. ?exam=it اگر بیاید،
           همان می‌ماند — لینک‌های هدفمند باید بتوانند انتخاب را باریک کنند. */
        $pre = array_filter(explode(',', (string) $req->query('exam', '')))
            ?: array_keys(Pricing::NAMES);

        $uid = $req->user()->id;

        /* مدیر/مدیرمحتوا رکورد واقعی خرید ندارد ولی Entitlements::for() همه‌ی
           رشته‌ها را به او می‌دهد (بالاتر، $owned) — این‌جا هم همان‌طور نشان
           می‌دهیم، وگرنه بالای صفحه «هنوز رشته‌ای نخریده‌اید» می‌گفت درحالی‌که
           کارت‌های پایین‌تر «فعال است» نشان می‌دادند. */
        $ents = $this->ent->isStaff($uid)
            ? collect(Pricing::NAMES)->keys()->map(fn ($e) => (object) [
                'exam' => $e, 'expires_at' => null, 'source' => 'staff',
            ])->values()
            : DB::table('zaban_entitlements')->where('user_id', $uid)->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('exam')->get(['exam', 'expires_at', 'source']);

        return view('buy.index', [
            'names'   => Pricing::NAMES,
            'owned'   => $owned,
            /* رشته‌های فعال با تاریخ اعتبار */
            'ents'    => $ents,
            /* پرداخت‌های کاربر — سفارشی که هیچ‌وقت به درگاه نرسید (بدون نشانه) نشان داده نمی‌شود */
            'orders'  => DB::table('zaban_orders')->where('user_id', $uid)
                ->where(fn ($q) => $q->whereNotNull('token')->orWhere('status', 'paid'))
                ->orderByDesc('id')->limit(30)
                ->get(['id', 'status', 'exams', 'payable', 'gateway', 'ref_id', 'rrn', 'gateway_code', 'created_at', 'paid_at']),
            /* درگاه‌های روشن؛ انتخاب پیش‌فرض: ?gw= (از «تلاش با درگاه دیگر») یا آخرین انتخاب فرم */
            'gateways' => $this->gateways->enabled(),
            'gw'       => $this->gateways->pick(old('gateway', $req->query('gw'))),
            'trial'   => app(\App\Services\Security\TrialQuota::class)->applies($uid)
                ? ['remaining' => app(\App\Services\Security\TrialQuota::class)->remaining($uid),
                   'cap'       => $this->ent->trialCap()]
                : null,
            'pre'     => array_values(array_diff(array_intersect($pre, array_keys(Pricing::NAMES)), $owned)),
            'bundles' => $this->pricing->bundles(),
            'launch'  => $this->pricing->launchOffer(),
            'stats'   => $this->pricing->examStats(),
            'until'   => $this->pricing->accessUntil(),
            'fake'    => $this->gateway->fake(),
        ]);
    }

    /** POST /buy — ساخت سفارش، گرفتن نشانه، رفتن به درگاه */
    public function start(Request $req)
    {
        $d = $req->validate([
            'exams'   => ['required', 'array', 'min:1', 'max:3'],
            'exams.*' => ['required', 'in:ce,it,cs'],
            'gateway' => ['nullable', 'string', 'in:' . IranKish::GATEWAY . ',' . Zarinpal::GATEWAY],
        ], ['exams.required' => 'دست‌کم یک رشته را انتخاب کنید.']);

        /* فقط از میان درگاه‌های روشن؛ درگاه خاموش‌شده هیچ‌وقت انتخاب نمی‌شود */
        $gw = $this->gateways->pick($d['gateway'] ?? null);
        if ($gw === null) {
            return back()->withInput()->with('buy_error', 'در حال حاضر هیچ درگاه پرداختی فعال نیست. کمی بعد دوباره سر بزنید.');
        }

        $user = $req->user();
        try {
            $r = $this->checkout->start(
                $user->id, $d['exams'], $user->mobile ?? null, $gw,
                $gw === Zarinpal::GATEWAY ? route('buy.zarinpal.return') : route('buy.return'),
            );
        } catch (\Throwable $e) {
            Log::warning('[Buy] start failed', ['user' => $user->id, 'gateway' => $gw, 'message' => $e->getMessage()]);
            return back()->withInput(['exams' => $d['exams'], 'gateway' => $gw])
                ->with('buy_error', $e->getMessage() . $this->tryOther($gw));
        }

        /* سفارش بی‌پرداخت (اعتبار خرید قبلی کل مبلغ را پوشانده): مستقیم نتیجه */
        if (!empty($r['free'])) {
            return redirect()->route('buy.result', ['order' => $r['order_id']]);
        }

        /* زرین‌پال: ریدایرکت ساده به StartPay */
        if ($r['method'] === 'get') {
            return redirect()->away($r['pay_url']);
        }

        /* ایران کیش فقط POST با فیلد tokenIdentity می‌پذیرد — فرم خودکار */
        return view('buy.redirect', ['url' => $r['pay_url'], 'token' => $r['token']]);
    }

    /**
     * GET /buy/zarinpal/return?Authority=…&Status=OK|NOK — برگشت از زرین‌پال.
     *
     * بیرون از middleware ورود است (ممکن است کوکی نشست همراهش نباشد)؛ سفارش با
     * authority پیدا می‌شود و نتیجه را verify سرور‌به‌سرور تعیین می‌کند.
     *
     * ورود خودکار نداریم: اگر کاربر وارد است و صاحب سفارش است، به صفحه‌ی نتیجه
     * می‌رود؛ وگرنه نتیجه همین‌جا نشان داده می‌شود — فقط برای سفارش تازه (کمتر از
     * ۲۴ ساعت)، تا کسی با یک آدرس برگشت قدیمی چیزی از سفارش دیگران نبیند.
     */
    public function zarinpalBack(Request $req)
    {
        $orderId = $this->checkout->handleZarinpalReturn(
            (string) $req->query('Authority', ''), (string) $req->query('Status', '')
        );

        $o = $orderId ? DB::table('zaban_orders')->where('id', $orderId)->first() : null;
        if (!$o) {
            return redirect()->route('buy')->with('buy_error',
                'تراکنش پیدا نشد. اگر مبلغی از حسابتان کسر شده، حداکثر ظرف ۷۲ ساعت برمی‌گردد؛ وگرنه با پشتیبانی تماس بگیرید.');
        }

        if ($req->user() && (int) $req->user()->id === (int) $o->user_id) {
            return redirect()->route('buy.result', ['order' => $o->id]);
        }

        if (now()->diffInHours($o->created_at, true) >= 24) {
            return redirect()->route('buy');
        }

        return $this->resultView($o, true);
    }

    /**
     * POST /buy/return — مرورگر کاربر از درگاه برمی‌گردد.
     * بدون auth و بدون CSRF (استثنا در AppServiceProvider)؛ اعتبار با نشانه و
     * تاییدیه‌ی سرور‌به‌سرور است، نه با این درخواست.
     */
    public function back(Request $req)
    {
        $orderId = $this->checkout->handleReturn($req->all());

        /* ریدایرکت GET — در GET کوکی نشست دوباره فرستاده می‌شود */
        return $orderId
            ? redirect()->route('buy.result', ['order' => $orderId])
            : redirect()->route('buy')->with('buy_error', 'نتیجه‌ی پرداخت شناسایی نشد. اگر مبلغی کسر شده، ظرف ۲۰ دقیقه به حسابتان برمی‌گردد.');
    }

    /** GET /buy/result/{order} — فقط صاحب سفارش */
    public function result(Request $req, int $order)
    {
        $o = DB::table('zaban_orders')->where('id', $order)->where('user_id', $req->user()->id)->first();
        abort_unless($o, 404);

        return $this->resultView($o, false);
    }

    private function resultView(object $o, bool $guest)
    {
        $other = $this->gateways->other($o->gateway);
        return view('buy.result', [
            'o'       => $o,
            'names'   => Pricing::NAMES,
            'msg'     => self::codeMessage((string) $o->gateway_code),
            'gwLabel' => Gateways::label($o->gateway),
            /* پیشنهاد درگاه دیگر فقط وقتی واقعاً روشن است */
            'other'   => $other,
            'otherLabel' => $other ? Gateways::label($other) : null,
            /* برگشت از زرین‌پال بدون نشست: صفحه‌ی نتیجه بدون لینک‌های حساب کاربری */
            'guest'   => $guest,
        ]);
    }

    /** « — می‌توانید با درگاه X پرداخت کنید.» اگر درگاه دیگری روشن است */
    private function tryOther(?string $gateway): string
    {
        $other = $this->gateways->other($gateway);
        return $other ? ' — لطفاً با درگاه ' . Gateways::label($other) . ' پرداخت کنید.' : '';
    }

    /** GET/POST /buy/fake — درگاه آزمایشی، فقط local + IRANKISH_FAKE */
    public function fake(Request $req)
    {
        abort_unless($this->gateway->fake(), 404);

        $token = (string) $req->input('tokenIdentity', $req->input('token', ''));
        $o = DB::table('zaban_orders')->where('token', $token)->first();
        abort_unless($o, 404);

        return view('buy.fake', ['o' => $o, 'names' => Pricing::NAMES]);
    }

    /** پیام فارسی کدهای پرکاربرد راهنمای ایران کیش */
    public static function codeMessage(string $code): ?string
    {
        return [
            '17'  => 'پرداخت را لغو کردید.',
            '5'   => 'از انجام تراکنش صرف‌نظر شد.',
            '55'  => 'رمز کارت نادرست بود.',
            '51'  => 'موجودی حساب کافی نبود.',
            '54'  => 'تاریخ انقضای کارت گذشته است.',
            '61'  => 'مبلغ بیش از سقف مجاز کارت است.',
            '75'  => 'تعداد دفعات ورود رمز اشتباه بیش از حد مجاز بود.',
            '14'  => 'اطلاعات کارت صحیح نبود.',
            '62'  => 'کارت محدود شده است.',
            '78'  => 'کارت فعال نیست.',
            '921' => 'مهلت پرداخت تمام شد. دوباره تلاش کنید.',
            'CHK' => 'اطلاعات برگشتی درگاه با سفارش جور نبود؛ پرداخت تأیید نشد.',
            'CNF' => 'درگاه تأیید نهایی را نداد.',
            'EXP' => 'از درگاه برنگشتید و مهلت پرداخت تمام شد.',
            'INQ' => 'پرداخت در استعلام درگاه تأیید نشد.',
            'NR'  => 'درگاه نتیجه‌ای برنگرداند.',
            'TOK' => 'ارتباط با درگاه برقرار نشد و به صفحه‌ی پرداخت نرسیدید.',
            /* زرین‌پال */
            'NOK' => 'پرداخت در زرین‌پال انجام نشد یا لغو شد.',
            '-51' => 'پرداخت در زرین‌پال ناموفق بود.',
            '-50' => 'مبلغ پرداخت‌شده با سفارش جور نبود؛ پرداخت تأیید نشد.',
            '-53' => 'این پرداخت متعلق به این پذیرنده نبود.',
            '-54' => 'شناسه‌ی پرداخت زرین‌پال نامعتبر بود.',
            'ZVF' => 'تأیید پرداخت از زرین‌پال گرفته نشد.',
        ][$code] ?? null;
    }
}
