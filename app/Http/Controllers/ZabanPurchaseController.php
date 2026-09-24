<?php

namespace App\Http\Controllers;

use App\Services\Entitlements;
use App\Services\Payment\Checkout;
use App\Services\Payment\IranKish;
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
 */
class ZabanPurchaseController extends Controller
{
    public function __construct(
        private Pricing $pricing,
        private Entitlements $ent,
        private Checkout $checkout,
        private IranKish $gateway,
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
        $pre   = array_filter(explode(',', (string) $req->query('exam', '')));

        $uid = $req->user()->id;
        return view('buy.index', [
            'names'   => Pricing::NAMES,
            'owned'   => $owned,
            /* رشته‌های فعال با تاریخ اعتبار */
            'ents'    => DB::table('zaban_entitlements')->where('user_id', $uid)->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderBy('exam')->get(['exam', 'expires_at', 'source']),
            /* پرداخت‌های کاربر — سفارشی که هیچ‌وقت به درگاه نرسید (بدون نشانه) نشان داده نمی‌شود */
            'orders'  => DB::table('zaban_orders')->where('user_id', $uid)->whereNotNull('token')
                ->orderByDesc('id')->limit(30)
                ->get(['id', 'status', 'exams', 'payable', 'rrn', 'gateway_code', 'created_at', 'paid_at']),
            'demo'    => ($dy = $this->ent->demoYear()) ? ['year' => $dy, 'exams' => $this->ent->demoExams($uid)] : null,
            'pre'     => array_values(array_diff(array_intersect($pre, array_keys(Pricing::NAMES)), $owned)),
            'bundles' => $this->pricing->bundles(),
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
        ], ['exams.required' => 'دست‌کم یک رشته را انتخاب کنید.']);

        $user = $req->user();
        try {
            $r = $this->checkout->start($user->id, $d['exams'], $user->mobile ?? null, route('buy.return'));
        } catch (\Throwable $e) {
            Log::warning('[Buy] start failed', ['user' => $user->id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('buy_error', $e->getMessage());
        }

        /* درگاه فقط POST با فیلد tokenIdentity می‌پذیرد — فرم خودکار */
        return view('buy.redirect', ['url' => $r['pay_url'], 'token' => $r['token']]);
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

        return view('buy.result', [
            'o'     => $o,
            'names' => Pricing::NAMES,
            'msg'   => self::codeMessage((string) $o->gateway_code),
        ]);
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
        ][$code] ?? null;
    }
}
