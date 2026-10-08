<?php

namespace App\Services\Payment;

use App\Services\Entitlements;
use App\Services\Pricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * خرید پکیج — ماشین حالت سفارش:
 *
 *   pending   ← سفارش ساخته شد و نشانه گرفته شد
 *   verifying ← کاربر از درگاه برگشت و ما «اولین نفری» بودیم که سفارش را برداشتیم
 *   paid      ← تاییدیه‌ی درگاه (ایران کیش: confirm کد ۰۰ / زرین‌پال: verify کد ۱۰۰ یا ۱۰۱)
 *               با مبلغ درست آمد؛ دسترسی داده شد
 *   failed    ← پرداخت نشد، یا یکی از بررسی‌ها رد شد (ایران کیش پول را برمی‌گرداند)
 *   expired   ← کاربر هیچ‌وقت از درگاه برنگشت
 *
 * قاعده‌های امنیت مالی:
 *   ۱) مبلغ فقط از Pricing سرور. مرورگر فقط «کدام رشته‌ها» را می‌گوید.
 *   ۲) دسترسی فقط بعد از confirm موفق با مبلغ برابر.
 *   ۳) گذر از pending اتمی است (UPDATE … WHERE status='pending')؛ دو برگشت
 *      هم‌زمان هرگز دو بار تایید یا دو بار دسترسی نمی‌سازند.
 *   ۴) اگر سرور بعد از confirm و قبل از ثبت بیفتد، سفارش در verifying می‌ماند
 *      و zaban:reconcile-payments با استعلام درستش می‌کند.
 *   ۵) مبلغ تأیید همیشه amount_rial خودِ سفارش در دیتابیس است.
 *
 * درگاه هر سفارش در ستون gateway است (irankish | zarinpal | manual | free)؛
 * برای زرین‌پال authority در token و authority ذخیره می‌شود و ref_id پاسخ verify
 * در ref_id. برای ایران کیش ref_id همان rrn است.
 */
class Checkout
{
    /** تاییدیه تا ۲۰ دقیقه پذیرفته می‌شود؛ حاشیه‌ی امن */
    public const CONFIRM_WINDOW_MIN = 18;

    public function __construct(
        private Pricing $pricing,
        private Entitlements $ent,
        private IranKish $gateway,
        private Zarinpal $zarinpal,
    ) {}

    /**
     * ساخت سفارش و گرفتن نشانه از درگاه انتخابی.
     * @param string $gateway   irankish | zarinpal (کنترلر از میان درگاه‌های روشن انتخاب کرده)
     * @param string $returnUrl آدرس برگشت همان درگاه
     * @return array{order_id:int, gateway:string, token:string, pay_url:string, method:string}
     *   method=post → فرم خودکار با tokenIdentity (ایران کیش)؛ method=get → ریدایرکت ساده (زرین‌پال)
     */
    public function start(int $userId, array $exams, ?string $mobile, string $gateway, string $returnUrl): array
    {
        if (!in_array($gateway, [IranKish::GATEWAY, Zarinpal::GATEWAY], true)) {
            throw new RuntimeException('درگاه پرداخت نامعتبر است.');
        }

        $q = $this->pricing->quote($exams, $this->ent->for($userId));
        if (!$q['billable']) {
            throw new RuntimeException('همه‌ی رشته‌های انتخابی از قبل برای شما فعال است.');
        }
        /* مبلغ صفر با اعتبار خرید قبلی ممکن است (مثلاً مدیر پکیج‌ها را طوری
           قیمت‌گذاری کند که رشته‌ی سوم رایگان شود). درگاه مبلغ صفر نمی‌گیرد،
           پس همین‌جا فعال می‌کنیم — نه اینکه کاربر را با خطا برگردانیم. */
        if ($q['payable'] <= 0) {
            return $this->grantFree($userId, $q);
        }

        $amountRial = (int) $q['payable'] * 10;      /* قیمت‌ها تومان‌اند؛ درگاه ریال می‌گیرد */

        $orderId = DB::table('zaban_orders')->insertGetId([
            'user_id'       => $userId,
            'status'        => 'pending',
            'exams'         => implode(',', $q['billable']),
            'list_price'    => $q['list_price'],
            'discount'      => $q['discount'],
            'payable'       => $q['payable'],
            'amount_rial'   => $amountRial,
            'price_version' => $q['price_version'],
            'expires_at'    => $this->pricing->accessUntil(),
            'gateway'       => $gateway,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        /* شناسه‌ی درخواست: یکتا، حداکثر ۲۰ نویسه (یکتایی را ایران کیش بررسی نمی‌کند) */
        $requestId = 'ZB' . $orderId . strtoupper(Str::random(max(4, 18 - strlen((string) $orderId))));
        $requestId = substr($requestId, 0, 20);

        if ($gateway === Zarinpal::GATEWAY) {
            return $this->startZarinpal($orderId, $userId, $q, $amountRial, $requestId, $mobile, $returnUrl);
        }

        Log::info('[pay] request', ['gateway' => IranKish::GATEWAY, 'order' => $orderId, 'user' => $userId, 'amount' => $amountRial]);
        try {
            $t = $this->gateway->tokenize($amountRial, $requestId, $returnUrl, $mobile);
        } catch (\Throwable $e) {
            DB::table('zaban_orders')->where('id', $orderId)
                ->update(['status' => 'failed', 'gateway_code' => 'TOK', 'request_id' => $requestId, 'updated_at' => now()]);
            Log::warning('[pay] token failed', ['gateway' => IranKish::GATEWAY, 'order' => $orderId, 'message' => $e->getMessage()]);
            throw $e;
        }

        DB::table('zaban_orders')->where('id', $orderId)->update([
            'request_id' => $requestId, 'token' => $t['token'],
            'authority'  => $t['token'],                 /* سازگاری با کد قدیمی */
            'updated_at' => now(),
        ]);

        Log::info('[pay] token', ['gateway' => IranKish::GATEWAY, 'order' => $orderId]);

        return ['order_id' => $orderId, 'gateway' => IranKish::GATEWAY, 'token' => $t['token'],
                'pay_url' => $this->gateway->payUrl(), 'method' => 'post'];
    }

    /** زرین‌پال: request ← authority روی سفارش ← ریدایرکت GET به StartPay */
    private function startZarinpal(int $orderId, int $userId, array $q, int $amountRial,
                                   string $requestId, ?string $mobile, string $returnUrl): array
    {
        $names = implode('، ', array_map(fn ($e) => Pricing::NAMES[$e] ?? $e, $q['billable']));
        /* زرین‌پال موبایل نامعتبر را با خطای -9 رد می‌کند؛ فقط شکل درست فرستاده می‌شود */
        $mobile = $mobile !== null ? strtr(trim($mobile), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                                                           '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']) : null;

        Log::info('[pay] request', ['gateway' => Zarinpal::GATEWAY, 'order' => $orderId, 'user' => $userId, 'amount' => $amountRial]);
        try {
            $authority = $this->zarinpal->request(
                $amountRial,
                $returnUrl,
                'پلتفرم زبان کنکور ارشد — ' . $names . ' — سفارش ' . $orderId,
                [
                    'mobile'   => ($mobile && preg_match('/^09\d{9}$/', $mobile)) ? $mobile : null,
                    'order_id' => (string) $orderId,
                ],
            );
        } catch (\Throwable $e) {
            DB::table('zaban_orders')->where('id', $orderId)
                ->update(['status' => 'failed', 'gateway_code' => 'TOK', 'request_id' => $requestId, 'updated_at' => now()]);
            throw $e;
        }

        DB::table('zaban_orders')->where('id', $orderId)->update([
            'request_id' => $requestId, 'token' => $authority, 'authority' => $authority,
            'updated_at' => now(),
        ]);
        Log::info('[pay] token', ['gateway' => Zarinpal::GATEWAY, 'order' => $orderId, 'authority' => $authority]);

        return ['order_id' => $orderId, 'gateway' => Zarinpal::GATEWAY, 'token' => $authority,
                'pay_url' => $this->zarinpal->startPayUrl($authority), 'method' => 'get'];
    }

    /**
     * برگشت مرورگر از زرین‌پال (GET ?Authority=…&Status=OK|NOK).
     *
     * Status فقط برای لاگ و پیام است؛ حرف آخر را verify سرور‌به‌سرور می‌زند —
     * حتی با NOK هم verify می‌پرسیم، چون اگر پولی گرفته شده باشد نباید گم شود،
     * و یک Status=OK جعلی هم بدون verify موفق هیچ دسترسی‌ای باز نمی‌کند.
     */
    public function handleZarinpalReturn(string $authority, string $status): ?int
    {
        $authority = trim($authority);
        if ($authority === '' || !preg_match('/^[A-Za-z0-9]{8,64}$/', $authority)) return null;

        $order = DB::table('zaban_orders')->where('token', $authority)
            ->where('gateway', Zarinpal::GATEWAY)->first();
        Log::info('[pay] callback', ['gateway' => Zarinpal::GATEWAY, 'authority' => $authority,
                                     'status' => $status, 'order' => $order->id ?? null]);
        if (!$order) {
            Log::warning('[pay] callback with unknown authority', ['gateway' => Zarinpal::GATEWAY, 'authority' => $authority]);
            return null;
        }

        /* قاعده‌ی ۳: فقط یک درخواست سفارش را از pending برمی‌دارد */
        $took = DB::table('zaban_orders')->where('id', $order->id)->where('status', 'pending')
            ->update(['status' => 'verifying', 'returned_at' => now(), 'updated_at' => now(),
                      'gateway_code' => $status === 'OK' ? 'OK' : 'NOK']);

        /* اولین برگشت، یا برگشت دوباره به سفارشی که verify قبلی‌اش به خطای شبکه خورد.
           verify زرین‌پال تکرارپذیر است (۱۰۱) و markPaid قفل‌دار است، پس دوباره زدنش
           هیچ‌وقت دو بار دسترسی نمی‌سازد. */
        if ($took || $order->status === 'verifying') {
            $this->settleZarinpal((int) $order->id, $status === 'OK' ? null : 'NOK');
        }
        return (int) $order->id;
    }

    /**
     * verify زرین‌پال برای سفارشی که در verifying است.
     * @return string 'paid' | 'failed' | 'retry'
     */
    private function settleZarinpal(int $orderId, ?string $failCode = null, string $failStatus = 'failed'): string
    {
        $order = DB::table('zaban_orders')->where('id', $orderId)->first();
        if (!$order || $order->status !== 'verifying' || !$order->token) return 'retry';

        /* قاعده‌ی ۵: مبلغ از دیتابیس */
        $v = $this->zarinpal->verify((string) $order->token, (int) $order->amount_rial);

        if ($v['ok']) {
            if ($v['card']) {
                DB::table('zaban_orders')->where('id', $orderId)->update(['masked_pan' => $v['card']]);
            }
            $this->markPaid($orderId, $v['ref_id']);
            Log::info('[pay] success', ['gateway' => Zarinpal::GATEWAY, 'order' => $orderId,
                                        'ref_id' => $v['ref_id'], 'already' => $v['already']]);
            return 'paid';
        }

        if ($v['definite']) {
            $this->fail($orderId, $failCode ?? (string) $v['code'], $failStatus);
            Log::warning('[pay] failed', ['gateway' => Zarinpal::GATEWAY, 'order' => $orderId, 'code' => $v['code']]);
            return 'failed';
        }

        /* خطای شبکه یا پاسخ نامعلوم: در verifying می‌ماند؛ reconcile دوباره امتحان می‌کند */
        return 'retry';
    }

    /**
     * برگشت مرورگر از درگاه (POST به revertUri). نشست کاربر اینجا نیست
     * (کوکی SameSite=Lax در POST از دامنه‌ی دیگر فرستاده نمی‌شود)؛ سفارش
     * فقط با نشانه پیدا می‌شود. همیشه شناسه‌ی سفارش را برمی‌گرداند تا صفحه‌ی نتیجه.
     */
    public function handleReturn(array $in): ?int
    {
        $token = (string) ($in['token'] ?? '');
        if ($token === '') return null;

        $order = DB::table('zaban_orders')->where('token', $token)
            ->where(fn ($q) => $q->where('gateway', IranKish::GATEWAY)->orWhereNull('gateway'))->first();
        Log::info('[pay] callback', ['gateway' => IranKish::GATEWAY, 'order' => $order->id ?? null,
                                     'code' => (string) ($in['responseCode'] ?? '')]);
        if (!$order) {
            Log::warning('[Checkout] return with unknown token');
            return null;
        }

        /* قاعده‌ی ۳: فقط یک درخواست سفارش را از pending برمی‌دارد */
        $took = DB::table('zaban_orders')->where('id', $order->id)->where('status', 'pending')
            ->update(['status' => 'verifying', 'returned_at' => now(), 'updated_at' => now(),
                      'gateway_code' => substr((string) ($in['responseCode'] ?? ''), 0, 4)]);
        if (!$took) return (int) $order->id;             /* قبلاً رسیدگی شده یا در حال رسیدگی است */

        $code = (string) ($in['responseCode'] ?? '');
        if ($code !== '00') {
            $this->fail($order->id, $code ?: 'NR');
            return (int) $order->id;
        }

        /* بررسی‌های پیش از تایید — اگر هر کدام رد شود تاییدیه نمی‌فرستیم و
           ایران کیش خودش پول را برمی‌گرداند */
        $ok = hash_equals((string) $order->request_id, (string) ($in['requestId'] ?? $in['RequestId'] ?? ''))
           && (int) ($in['amount'] ?? -1) === (int) $order->amount_rial
           && ($this->gateway->fake() || (string) ($in['acceptorId'] ?? '') === (string) config('irankish.acceptor_id'));

        $rrn  = preg_replace('/\D/', '', (string) ($in['retrievalReferenceNumber'] ?? ''));
        $stan = preg_replace('/\D/', '', (string) ($in['systemTraceAuditNumber'] ?? ''));

        if (!$ok || $rrn === '' || $stan === '') {
            Log::error('[Checkout] return failed pre-checks', ['order' => $order->id]);
            $this->fail($order->id, 'CHK');
            return (int) $order->id;
        }

        /* rrn و stan پیش از تایید ذخیره می‌شوند تا اگر سرور وسط کار افتاد،
           reconcile بتواند دوباره تایید بفرستد */
        DB::table('zaban_orders')->where('id', $order->id)->update([
            'rrn' => $rrn, 'stan' => $stan,
            'masked_pan' => substr((string) ($in['maskedPan'] ?? ''), 0, 19) ?: null,
            'updated_at' => now(),
        ]);

        $this->settle((int) $order->id);
        return (int) $order->id;
    }

    /**
     * ارسال تاییدیه برای سفارشی که در verifying است، و در صورت موفقیت
     * ثبت پرداخت و دادن دسترسی در یک تراکنش.
     */
    public function settle(int $orderId): void
    {
        $order = DB::table('zaban_orders')->where('id', $orderId)->first();
        if (!$order || $order->status !== 'verifying' || !$order->rrn || !$order->stan) return;

        try {
            $c = $this->gateway->confirm($order->token, $order->rrn, $order->stan, (int) $order->amount_rial);
        } catch (\Throwable $e) {
            /* خطای شبکه: در verifying می‌ماند؛ reconcile دوباره امتحان می‌کند */
            Log::error('[Checkout] confirm error, left for reconcile', ['order' => $orderId, 'message' => $e->getMessage()]);
            return;
        }

        if ($c['ok']) {
            $this->markPaid($orderId);
        } else {
            $this->fail($orderId, $c['code'] ?: 'CNF');
        }
    }

    /**
     * تطبیق سفارش‌های گیرکرده — از zaban:reconcile-payments.
     * @return string خلاصه‌ی کاری که شد
     */
    public function reconcile(object $order): string
    {
        if ($order->gateway === Zarinpal::GATEWAY) {
            return $this->reconcileZarinpal($order);
        }

        $age = now()->diffInMinutes($order->returned_at ?? $order->created_at, true);

        /* verifying و هنوز در مهلت تاییدیه: دوباره confirm */
        if ($order->status === 'verifying' && $order->rrn && $age < self::CONFIRM_WINDOW_MIN) {
            $this->settle((int) $order->id);
            return 'retried-confirm';
        }

        /* خارج از مهلت: فقط استعلام حرف آخر را می‌زند */
        if ($age >= 25 && $order->token && !$this->gateway->fake()) {
            try {
                $inq = $this->gateway->inquiry($order->token);
            } catch (\Throwable $e) {
                return 'inquiry-error';
            }
            if ($inq && $inq['verified'] && !$inq['reversed'] && $inq['code'] === '00'
                && (int) $inq['amount'] === (int) $order->amount_rial) {
                DB::table('zaban_orders')->where('id', $order->id)->update([
                    'rrn' => $inq['rrn'] ?? $order->rrn, 'stan' => $inq['stan'] ?? $order->stan,
                    'masked_pan' => $inq['masked_pan'] ?? $order->masked_pan,
                ]);
                $this->markPaid((int) $order->id);
                return 'paid-by-inquiry';
            }
            $this->fail((int) $order->id, $order->status === 'pending' ? 'EXP' : 'INQ',
                        $order->status === 'pending' ? 'expired' : 'failed');
            return 'closed';
        }

        return 'waiting';
    }

    /**
     * زرین‌پال استعلام جدا ندارد؛ verify خودش استعلام است (۱۰۰/۱۰۱ یعنی پول رسیده).
     *   pending  — کاربر برنگشته. تا ۲۵ دقیقه ممکن است هنوز در صفحه‌ی درگاه باشد، پس
     *              دست نمی‌زنیم؛ بعد از آن سفارش را برمی‌داریم و verify می‌پرسیم.
     *   verifying — verify قبلی به خطای شبکه خورده؛ هر بار دوباره، و اگر تا ۴۵ دقیقه
     *              جواب قطعی نیامد بسته می‌شود (زرین‌پال تراکنش تأییدنشده را برمی‌گرداند).
     */
    private function reconcileZarinpal(object $order): string
    {
        $age = now()->diffInMinutes($order->returned_at ?? $order->created_at, true);

        if ($order->status === 'pending') {
            if ($age < 25) return 'waiting';
            $took = DB::table('zaban_orders')->where('id', $order->id)->where('status', 'pending')
                ->update(['status' => 'verifying', 'updated_at' => now()]);
            if (!$took) return 'waiting';
            $r = $this->settleZarinpal((int) $order->id, 'EXP', 'expired');
        } elseif ($order->status === 'verifying') {
            $r = $this->settleZarinpal((int) $order->id);
        } else {
            return 'waiting';
        }

        if ($r === 'paid')   return 'paid-by-inquiry';
        if ($r === 'failed') return 'closed';

        if (now()->diffInMinutes($order->created_at, true) >= 45) {
            $this->fail((int) $order->id, 'ZVF');
            return 'closed';
        }
        return 'inquiry-error';
    }

    /* ---------------------------------------------------------------- */

    /**
     * سفارش بی‌پرداخت: ثبت می‌شود و دسترسی فوراً داده می‌شود.
     * @return array{order_id:int, token:string, pay_url:string, free:true}
     */
    private function grantFree(int $userId, array $q): array
    {
        $orderId = DB::table('zaban_orders')->insertGetId([
            'user_id'       => $userId,
            'status'        => 'pending',
            'exams'         => implode(',', $q['billable']),
            'list_price'    => $q['list_price'],
            'discount'      => $q['discount'],
            'payable'       => 0,
            'amount_rial'   => 0,
            'price_version' => $q['price_version'],
            'expires_at'    => $this->pricing->accessUntil(),
            'gateway'       => 'free',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::transaction(function () use ($orderId, $userId, $q) {
            DB::table('zaban_orders')->where('id', $orderId)->update([
                'status' => 'paid', 'gateway_code' => '00',
                'paid_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($q['billable'] as $exam) {
                $this->ent->grant($userId, $exam, $this->pricing->accessUntil(), 'purchase', $orderId);
            }
        });
        $this->ent->forget($userId);
        Log::info('[Checkout] free grant', ['order' => $orderId, 'user' => $userId]);

        return ['order_id' => $orderId, 'token' => '', 'pay_url' => '', 'free' => true];
    }

    /**
     * فعال‌سازی دستی یک سفارش — کارت به کارت یا تراکنشی که از مهلت استعلام
     * ایران کیش (۷ روز) گذشته و فقط با رسید قابل تأیید است.
     *
     * همان مسیر markPaid را می‌رود تا دسترسی و تاریخچه دقیقاً مثل پرداخت
     * عادی ثبت شود؛ فقط منبعش staff می‌ماند و یادداشت مدیر کنارش می‌نشیند.
     */
    public function activateManually(int $orderId, string $note, ?int $byUserId = null): void
    {
        DB::transaction(function () use ($orderId, $note, $byUserId) {
            $order = DB::table('zaban_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) throw new RuntimeException('سفارش پیدا نشد.');
            if ($order->status === 'paid') throw new RuntimeException('این سفارش از قبل پرداخت‌شده است.');

            DB::table('zaban_orders')->where('id', $orderId)->update([
                'status' => 'paid', 'gateway' => 'manual', 'gateway_code' => '00',
                'paid_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_filter(explode(',', $order->exams)) as $exam) {
                $this->ent->grant((int) $order->user_id, $exam, $order->expires_at,
                                  'staff', (int) $order->id, $note);
            }
        });

        $uid = (int) DB::table('zaban_orders')->where('id', $orderId)->value('user_id');
        $this->ent->forget($uid);
        Log::warning('[Checkout] manual activation', [
            'order' => $orderId, 'user' => $uid, 'by' => $byUserId, 'note' => $note,
        ]);
    }

    /**
     * ثبت پرداخت و دادن دسترسی — یک‌باره: ردیف سفارش قفل می‌شود و اگر از قبل
     * paid باشد هیچ کاری نمی‌کند، پس برگشت هم‌زمان یا تکراری دو بار حساب نمی‌شود.
     * $refId: کد پیگیری درگاه (زرین‌پال ref_id)؛ برای ایران کیش rrn.
     */
    private function markPaid(int $orderId, ?string $refId = null): void
    {
        DB::transaction(function () use ($orderId, $refId) {
            $order = DB::table('zaban_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order || $order->status === 'paid') return;

            DB::table('zaban_orders')->where('id', $orderId)->update([
                'status' => 'paid', 'gateway_code' => '00', 'ref_id' => $refId ?: $order->rrn,
                'paid_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_filter(explode(',', $order->exams)) as $exam) {
                $this->ent->grant((int) $order->user_id, $exam, $order->expires_at, 'purchase', (int) $order->id);
            }
        });
        $uid = (int) DB::table('zaban_orders')->where('id', $orderId)->value('user_id');
        $this->ent->forget($uid);
        Log::info('[Checkout] paid', ['order' => $orderId, 'user' => $uid,
            'gateway' => DB::table('zaban_orders')->where('id', $orderId)->value('gateway')]);
    }

    private function fail(int $orderId, string $code, string $status = 'failed'): void
    {
        DB::table('zaban_orders')->where('id', $orderId)->where('status', '!=', 'paid')
            ->update(['status' => $status, 'gateway_code' => substr($code, 0, 4), 'updated_at' => now()]);
    }
}
