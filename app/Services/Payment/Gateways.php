<?php

namespace App\Services\Payment;

/**
 * فهرست درگاه‌ها: کدام روشن است، نام فارسی، و «درگاه دیگر» برای پیشنهاد در پیام خطا.
 * صفحه‌ی خرید، کنترلر، پنل مدیر و preflight همه از همین‌جا می‌خوانند.
 */
class Gateways
{
    public const LABELS = [
        'irankish' => 'ایران کیش',
        'zarinpal' => 'زرین‌پال',
        'manual'   => 'دستی (مدیر)',
        'free'     => 'بدون پرداخت',
    ];

    public const STATUS = [
        'pending'   => 'در انتظار پرداخت',
        'verifying' => 'در حال تأیید',
        'paid'      => 'پرداخت‌شده',
        'failed'    => 'ناموفق',
        'expired'   => 'منقضی',
    ];

    public function __construct(private IranKish $irankish, private Zarinpal $zarinpal) {}

    /** درگاه‌های قابل انتخاب، به ترتیب نمایش: ['irankish' => 'ایران کیش', …] */
    public function enabled(): array
    {
        $out = [];
        if (config('gateways.irankish.enabled', true)
            && ($this->irankish->fake() || trim((string) config('irankish.terminal_id', '')) !== '')) {
            $out[IranKish::GATEWAY] = self::LABELS['irankish'];
        }
        if ($this->zarinpal->enabled()) {
            $out[Zarinpal::GATEWAY] = self::LABELS['zarinpal'];
        }
        return $out;
    }

    /** درگاه انتخابی کاربر اگر روشن است، وگرنه اولین درگاه روشن؛ null یعنی هیچ درگاهی نیست */
    public function pick(?string $wanted): ?string
    {
        $on = $this->enabled();
        if ($wanted !== null && isset($on[$wanted])) return $wanted;
        return array_key_first($on);
    }

    /** نام فارسی درگاه دیگرِ روشن (برای «… با درگاه X پرداخت کنید») */
    public function other(?string $gateway): ?string
    {
        foreach ($this->enabled() as $k => $label) {
            if ($k !== $gateway) return $k;
        }
        return null;
    }

    public static function label(?string $gateway): string
    {
        /* ردیف‌های قدیمی بدون gateway همه ایران کیش بوده‌اند */
        return self::LABELS[$gateway ?: 'irankish'] ?? (string) $gateway;
    }

    public static function status(?string $status): string
    {
        return self::STATUS[$status] ?? (string) $status;
    }
}
