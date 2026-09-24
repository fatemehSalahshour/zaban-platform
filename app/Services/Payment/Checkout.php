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
 *   paid      ← تاییدیه‌ی ایران کیش کد ۰۰ و مبلغ درست داد؛ دسترسی داده شد
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
 */
class Checkout
{
    /** تاییدیه تا ۲۰ دقیقه پذیرفته می‌شود؛ حاشیه‌ی امن */
    public const CONFIRM_WINDOW_MIN = 18;

    public function __construct(
        private Pricing $pricing,
        private Entitlements $ent,
        private IranKish $gateway,
    ) {}

    /**
     * ساخت سفارش و گرفتن نشانه.
     * @return array{order_id:int, token:string, pay_url:string}
     */
    public function start(int $userId, array $exams, ?string $mobile, string $revertUri): array
    {
        $q = $this->pricing->quote($exams, $this->ent->for($userId));
        if (!$q['billable']) {
            throw new RuntimeException('همه‌ی رشته‌های انتخابی از قبل برای شما فعال است.');
        }
        if ($q['payable'] <= 0) {
            throw new RuntimeException('مبلغ سفارش نامعتبر است.');
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
            'gateway'       => IranKish::GATEWAY,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        /* شناسه‌ی درخواست: یکتا، حداکثر ۲۰ نویسه (یکتایی را ایران کیش بررسی نمی‌کند) */
        $requestId = 'ZB' . $orderId . strtoupper(Str::random(max(4, 18 - strlen((string) $orderId))));
        $requestId = substr($requestId, 0, 20);

        try {
            $t = $this->gateway->tokenize($amountRial, $requestId, $revertUri, $mobile);
        } catch (\Throwable $e) {
            DB::table('zaban_orders')->where('id', $orderId)
                ->update(['status' => 'failed', 'request_id' => $requestId, 'updated_at' => now()]);
            throw $e;
        }

        DB::table('zaban_orders')->where('id', $orderId)->update([
            'request_id' => $requestId, 'token' => $t['token'],
            'authority'  => $t['token'],                 /* سازگاری با کد قدیمی */
            'updated_at' => now(),
        ]);

        return ['order_id' => $orderId, 'token' => $t['token'], 'pay_url' => $this->gateway->payUrl()];
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

        $order = DB::table('zaban_orders')->where('token', $token)->first();
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

    /* ---------------------------------------------------------------- */

    private function markPaid(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('zaban_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order || $order->status === 'paid') return;

            DB::table('zaban_orders')->where('id', $orderId)->update([
                'status' => 'paid', 'gateway_code' => '00', 'ref_id' => $order->rrn,
                'paid_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_filter(explode(',', $order->exams)) as $exam) {
                $this->ent->grant((int) $order->user_id, $exam, $order->expires_at, 'purchase', (int) $order->id);
            }
        });
        $uid = (int) DB::table('zaban_orders')->where('id', $orderId)->value('user_id');
        $this->ent->forget($uid);
        Log::info('[Checkout] paid', ['order' => $orderId, 'user' => $uid]);
    }

    private function fail(int $orderId, string $code, string $status = 'failed'): void
    {
        DB::table('zaban_orders')->where('id', $orderId)->where('status', '!=', 'paid')
            ->update(['status' => $status, 'gateway_code' => substr($code, 0, 4), 'updated_at' => now()]);
    }
}
