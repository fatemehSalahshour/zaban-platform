<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * درگاه ایران کیش — طبق «راهنمای استفاده از درگاه پرداخت اینترنتی»
 * (IPG_TECHNICALGUIDE، بازنگری ۹، ۱۳۹۹/۱۰/۰۹).
 *
 *   ۱) tokenize  → api/v3/tokenization/make   (پاکت دیجیتال + محدودیت آی‌پی)
 *   ۲) مرورگر با POST به iuiv3/IPG/Index/ با tokenIdentity
 *   ۳) نتیجه با POST مرورگر به revertUri برمی‌گردد
 *   ۴) confirm   → api/v3/confirmation/purchase — حداکثر ۲۰ دقیقه بعد از
 *      پرداخت؛ وگرنه ایران کیش خودکار پول را برمی‌گرداند
 *   ۵) inquiry   → api/v3/inquiry/single — تا ۷ روز، برای تطبیق
 *
 * همه‌ی مبلغ‌ها ریال‌اند.
 */
class IranKish
{
    public const GATEWAY = 'irankish';

    public function fake(): bool
    {
        return (bool) config('irankish.fake') && app()->environment('local');
    }

    /** آدرسی که مرورگر با POST و فیلد tokenIdentity به آن فرستاده می‌شود */
    public function payUrl(): string
    {
        return $this->fake()
            ? route('buy.fake')
            : rtrim(config('irankish.base_url'), '/') . '/iuiv3/IPG/Index/';
    }

    /**
     * دریافت نشانه‌ی پرداخت.
     * @return array{token:string, raw:array}
     */
    public function tokenize(int $amountRial, string $requestId, string $revertUri, ?string $mobile = null): array
    {
        if ($this->fake()) {
            return ['token' => 'FAKE' . strtoupper(Str::random(28)), 'raw' => ['fake' => true]];
        }

        $cfg = $this->config();
        $request = [
            'transactionType'  => 'Purchase',
            'terminalId'       => $cfg['terminal_id'],
            'acceptorId'       => $cfg['acceptor_id'],
            'amount'           => $amountRial,
            'revertUri'        => $revertUri,
            'requestId'        => $requestId,
            'requestTimestamp' => time(),
        ];
        /* ذخیره‌ی کارت دارنده در درگاه — الگوی خود ایران کیش: 989XXXXXXXXX */
        if ($mobile && preg_match('/^09(\d{9})$/', $mobile, $m)) {
            $request['cmsPreservationId'] = '989' . $m[1];
        }

        $res = $this->post('/api/v3/tokenization/make', [
            'authenticationEnvelope' => $this->envelope($amountRial),
            'request'                => $request,
        ]);

        if (!($res['status'] ?? false) || ($res['responseCode'] ?? null) !== '00' || empty($res['result']['token'])) {
            Log::warning('[IranKish] tokenize failed', ['code' => $res['responseCode'] ?? null,
                                                        'desc' => $res['description'] ?? null,
                                                        'request_id' => $requestId]);
            throw new RuntimeException('درگاه پرداخت درخواست را نپذیرفت (کد ' . ($res['responseCode'] ?? '—') . ').');
        }

        return ['token' => (string) $res['result']['token'], 'raw' => $res];
    }

    /**
     * ارسال تاییدیه. فقط وقتی true که ایران کیش کد ۰۰ داده و مبلغ همان باشد.
     * @return array{ok:bool, code:?string, amount:?int, raw:array}
     */
    public function confirm(string $token, string $rrn, string $stan, int $expectAmountRial): array
    {
        if ($this->fake()) {
            return ['ok' => true, 'code' => '00', 'amount' => $expectAmountRial, 'raw' => ['fake' => true]];
        }

        $res = $this->post('/api/v3/confirmation/purchase', [
            'terminalId'               => $this->config()['terminal_id'],
            'retrievalReferenceNumber' => $rrn,
            'systemTraceAuditNumber'   => $stan,
            'tokenIdentity'            => $token,
        ]);

        $code   = $res['result']['responseCode'] ?? $res['responseCode'] ?? null;
        $amount = isset($res['result']['amount']) ? (int) $res['result']['amount'] : null;
        $ok     = ($res['status'] ?? false) === true
               && ($res['responseCode'] ?? null) === '00'
               && $code === '00'
               && $amount === $expectAmountRial;

        if (!$ok) {
            Log::warning('[IranKish] confirm not ok', ['code' => $code, 'amount' => $amount,
                                                       'expect' => $expectAmountRial]);
        }
        return ['ok' => $ok, 'code' => $code, 'amount' => $amount, 'raw' => $res];
    }

    /**
     * استعلام با شناسه‌ی نشانه (findOption = 2). null اگر پیدا نشد.
     * @return array{verified:bool, reversed:bool, code:?string, amount:?int, rrn:?string, stan:?string, masked_pan:?string}|null
     */
    public function inquiry(string $token): ?array
    {
        if ($this->fake()) {
            return null;
        }

        $res = $this->post('/api/v3/inquiry/single', [
            'passPhrase'    => $this->config()['pass_phrase'],
            'terminalId'    => $this->config()['terminal_id'],
            'tokenIdentity' => $token,
            'findOption'    => 2,
        ]);

        $t = $res['result'] ?? null;
        if (is_array($t) && array_is_list($t)) $t = $t[0] ?? null;   /* چند رکورد → اولی */
        if (!($res['status'] ?? false) || !is_array($t)) return null;

        return [
            'verified'   => (bool) ($t['isVerified'] ?? false),
            'reversed'   => (bool) ($t['isReversed'] ?? false),
            'code'       => $t['responseCode'] ?? null,
            'amount'     => isset($t['amount']) ? (int) $t['amount'] : null,
            'rrn'        => $t['retrievalReferenceNumber'] ?? null,
            'stan'       => $t['systemTraceAuditNumber'] ?? null,
            'masked_pan' => $t['maskedPan'] ?? null,
        ];
    }

    /* ================================================================
     |  پاکت دیجیتال — راهنما، «نحوه تولید پاکت دیجیتال»
     |  رشته‌ی پایه (خرید عادی): شماره‌پایانه + کلمه‌عبور + مبلغ۱۲رقمی + "00"
     |  → به‌عنوان HEX به بایت → AES-128-CBC/PKCS7 با کلید و بردار تصادفی
     |  → SHA256 از متن رمز → [کلید AES (۱۶ بایت) + هش (۳۲ بایت)]
     |  → RSA با کلید عمومی ایران کیش → data (HEX)، iv (HEX)
     |  مراحل AES و SHA با مثال خود راهنما آزموده شده‌اند.
     * ================================================================ */
    public function envelope(int $amountRial, ?string $aesKey = null, ?string $iv = null): array
    {
        $cfg  = $this->config();
        $base = $cfg['terminal_id'] . $cfg['pass_phrase'] . str_pad((string) $amountRial, 12, '0', STR_PAD_LEFT) . '00';

        if (strlen($base) % 2 !== 0 || !ctype_xdigit($base)) {
            throw new RuntimeException('شماره پایانه یا کلمه عبور ایران کیش قالب درستی ندارد.');
        }

        $key = $aesKey ?? random_bytes(16);
        $iv  = $iv ?? random_bytes(16);

        $cipher = openssl_encrypt(hex2bin($base), 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) throw new RuntimeException('رمزنگاری AES ناموفق بود.');

        $block = $key . hash('sha256', $cipher, true);           // ۱۶ + ۳۲ = ۴۸ بایت

        if (!openssl_public_encrypt($block, $rsa, $this->publicKey(), OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('رمزنگاری RSA با کلید عمومی ایران کیش ناموفق بود.');
        }

        return ['data' => strtoupper(bin2hex($rsa)), 'iv' => strtoupper(bin2hex($iv))];
    }

    /* ---------------------------------------------------------------- */

    private function post(string $path, array $body): array
    {
        try {
            $r = Http::timeout(config('irankish.timeout', 15))
                ->acceptJson()->asJson()
                ->post(rtrim(config('irankish.base_url'), '/') . $path, $body);
        } catch (\Throwable $e) {
            Log::error('[IranKish] connection failed', ['path' => $path, 'message' => $e->getMessage()]);
            throw new RuntimeException('ارتباط با درگاه پرداخت برقرار نشد.', 0, $e);
        }
        return $r->json() ?? [];
    }

    private function config(): array
    {
        $c = config('irankish');
        foreach (['terminal_id', 'acceptor_id', 'pass_phrase'] as $k) {
            if (empty($c[$k])) throw new RuntimeException("تنظیم ایران کیش ناقص است: $k");
        }
        return $c;
    }

    private function publicKey(): string
    {
        $path = base_path(config('irankish.public_key_path'));
        if (!is_file($path)) throw new RuntimeException('فایل کلید عمومی ایران کیش پیدا نشد: ' . $path);
        return (string) file_get_contents($path);
    }
}
