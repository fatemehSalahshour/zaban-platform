<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * درگاه زرین‌پال — API نسخه‌ی ۴.
 *
 *   ۱) request → POST {base}/v4/payment/request.json   → data.code=100 و data.authority
 *   ۲) مرورگر با GET به {base}/StartPay/{authority}
 *   ۳) برگشت با GET به callback_url?Authority=…&Status=OK|NOK
 *   ۴) verify  → POST {base}/v4/payment/verify.json    → 100 موفق، 101 قبلاً تأیید شده
 *
 * همه‌ی مبلغ‌ها ریال‌اند (currency=IRR).
 *
 * ⚠ بررسی گواهی SSL خاموش نمی‌شود: پاسخ verify تعیین می‌کند دسترسی باز شود یا
 *   نه؛ اگر بررسی خاموش باشد پاسخ جعلی «موفق» پذیرفته می‌شود. اگر CA سرور کهنه
 *   است، فایل CA به‌روز با ZARINPAL_CA_BUNDLE معرفی می‌شود.
 */
class Zarinpal
{
    public const GATEWAY = 'zarinpal';

    /** پاسخ‌های verify که یعنی «این پرداخت قطعاً انجام نشده» — بقیه دوباره امتحان می‌شوند */
    public const DEFINITE_FAIL = [-50, -51, -53, -54];

    public function enabled(): bool
    {
        return (bool) config('gateways.zarinpal.enabled')
            && $this->merchant() !== '';
    }

    public function sandbox(): bool
    {
        return (bool) config('gateways.zarinpal.sandbox');
    }

    public function startPayUrl(string $authority): string
    {
        return $this->base() . '/StartPay/' . rawurlencode($authority);
    }

    /**
     * درخواست پرداخت.
     * @return string authority
     * @throws RuntimeException با پیام فارسی قابل نمایش به کاربر
     */
    public function request(int $amountRial, string $callbackUrl, string $description, array $meta = []): string
    {
        $body = [
            'merchant_id'  => $this->merchant(),
            'amount'       => $amountRial,
            'currency'     => 'IRR',
            'callback_url' => $callbackUrl,
            'description'  => mb_substr($description, 0, 250),
        ];
        $meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');
        if ($meta) $body['metadata'] = $meta;

        $r = $this->post('/v4/payment/request.json', $body);
        $data = is_array($r['json']['data'] ?? null) ? $r['json']['data'] : [];
        $code = (int) ($data['code'] ?? 0);

        if ($code === 100 && !empty($data['authority'])) {
            return (string) $data['authority'];
        }

        Log::warning('[pay] request failed', [
            'gateway' => self::GATEWAY, 'amount' => $amountRial,
            'net_error' => $r['error'], 'http' => $r['http'],
            'code' => $code ?: null, 'errors' => $r['json']['errors'] ?? null,
            'body' => mb_substr((string) $r['raw'], 0, 500),
        ]);

        throw new RuntimeException($this->errorText($r));
    }

    /**
     * تأیید پرداخت. مبلغ را صدازننده از دیتابیس می‌دهد، نه از درخواست کاربر.
     * @return array{ok:bool, already:bool, definite:bool, code:?int, ref_id:?string, card:?string}
     *   ok       — پول دریافت شده (۱۰۰ یا ۱۰۱)
     *   definite — شکست قطعی (پرداخت انجام نشده)؛ false یعنی خطای شبکه/موقت و باید دوباره امتحان شود
     */
    public function verify(string $authority, int $amountRial): array
    {
        $r = $this->post('/v4/payment/verify.json', [
            'merchant_id' => $this->merchant(),
            'amount'      => $amountRial,
            'authority'   => $authority,
        ]);
        $data = is_array($r['json']['data'] ?? null) ? $r['json']['data'] : [];
        $code = (int) ($data['code'] ?? ($r['json']['errors']['code'] ?? 0));

        $ok = in_array($code, [100, 101], true);
        $out = [
            'ok'       => $ok,
            'already'  => $code === 101,
            'definite' => !$ok && $r['error'] === null && in_array($code, self::DEFINITE_FAIL, true),
            'code'     => $code ?: null,
            'ref_id'   => isset($data['ref_id']) && $data['ref_id'] !== '' ? (string) $data['ref_id'] : null,
            'card'     => isset($data['card_pan']) ? mb_substr((string) $data['card_pan'], 0, 19) : null,
        ];

        if (!$ok) {
            Log::warning('[pay] verify not ok', [
                'gateway' => self::GATEWAY, 'authority' => $authority, 'amount' => $amountRial,
                'net_error' => $r['error'], 'http' => $r['http'], 'code' => $out['code'],
                'errors' => $r['json']['errors'] ?? null, 'body' => mb_substr((string) $r['raw'], 0, 500),
            ]);
        }
        return $out;
    }

    /* ---------------------------------------------------------------- */

    private function merchant(): string
    {
        return trim((string) config('gateways.zarinpal.merchant_id', ''));
    }

    private function base(): string
    {
        return $this->sandbox() ? 'https://sandbox.zarinpal.com/pg' : 'https://payment.zarinpal.com/pg';
    }

    /**
     * @return array{json:array, raw:?string, http:int, error:?string}
     */
    private function post(string $path, array $body): array
    {
        try {
            $res = Http::timeout((int) config('gateways.zarinpal.timeout', 25))
                ->connectTimeout((int) config('gateways.zarinpal.connect_timeout', 10))
                ->withOptions($this->tls())
                ->acceptJson()->asJson()
                ->post($this->base() . $path, $body);
        } catch (ConnectionException $e) {
            /* curl 35/60/28 … — پیام کامل در لاگ، پیام ساده به کاربر */
            return ['json' => [], 'raw' => null, 'http' => 0, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['json' => [], 'raw' => null, 'http' => 0, 'error' => get_class($e) . ': ' . $e->getMessage()];
        }

        $json = $res->json();
        return [
            'json'  => is_array($json) ? $json : [],
            'raw'   => $res->body(),
            'http'  => $res->status(),
            'error' => null,
        ];
    }

    /** گزینه‌ی TLS: همیشه بررسی گواهی روشن؛ فقط فایل CA ممکن است عوض شود */
    private function tls(): array
    {
        $ca = (string) config('gateways.zarinpal.ca_bundle', '');
        if ($ca !== '' && is_readable($ca)) {
            return ['verify' => $ca];
        }
        if ($ca !== '') {
            Log::warning('[pay] ZARINPAL_CA_BUNDLE not readable, using system CA', ['gateway' => self::GATEWAY, 'path' => $ca]);
        }
        return ['verify' => true];
    }

    private function errorText(array $r): string
    {
        if ($r['error'] !== null) {
            return 'اتصال به درگاه زرین‌پال برقرار نشد.';
        }
        $code = (int) ($r['json']['errors']['code'] ?? 0);
        return [
            -9  => 'اطلاعات ارسالی به درگاه زرین‌پال نامعتبر بود.',
            -10 => 'تنظیمات درگاه زرین‌پال (مرچنت یا آی‌پی) معتبر نیست.',
            -11 => 'درگاه زرین‌پال فعال نیست.',
            -12 => 'تلاش‌های زیاد در زمان کوتاه؛ کمی بعد دوباره تلاش کنید.',
            -15 => 'درگاه زرین‌پال به حالت تعلیق درآمده است.',
            -16 => 'سطح تأیید پذیرنده در زرین‌پال کافی نیست.',
        ][$code] ?? ('درگاه زرین‌پال درخواست را نپذیرفت' . ($code ? " (کد {$code})" : '') . '.');
    }
}
