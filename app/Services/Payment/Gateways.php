<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\DB;

/**
 * فهرست درگاه‌ها: کدام روشن است، نام فارسی، و «درگاه دیگر» برای پیشنهاد در پیام خطا.
 * صفحه‌ی خرید، کنترلر، پنل مدیر و preflight همه از همین‌جا می‌خوانند.
 *
 * دو لایه:
 *   ۱) «آماده» — از .env: مرچنت/پایانه تنظیم شده و با IRANKISH_ENABLED / ZARINPAL_ENABLED
 *      خاموش نشده. درگاهی که آماده نیست هیچ‌وقت نشان داده نمی‌شود.
 *   ۲) «روشن» — انتخاب مدیر در پنل (تنظیمات ← درگاه پرداخت)، در zaban_meta:
 *      gateways_on = 'irankish,zarinpal' و gateway_default = 'zarinpal'.
 *      تا مدیر چیزی ذخیره نکرده، همه‌ی درگاه‌های آماده روشن‌اند.
 */
class Gateways
{
    public const LABELS = [
        'irankish' => 'ایران کیش',
        'zarinpal' => 'زرین‌پال',
        'manual'   => 'دستی (مدیر)',
        'free'     => 'بدون پرداخت',
    ];

    /** درگاه‌های قابل انتخاب در پنل، به ترتیب پیش‌فرض نمایش */
    public const SELECTABLE = ['irankish', 'zarinpal'];

    public const STATUS = [
        'pending'   => 'در انتظار پرداخت',
        'verifying' => 'در حال تأیید',
        'paid'      => 'پرداخت‌شده',
        'failed'    => 'ناموفق',
        'expired'   => 'منقضی',
    ];

    private const K_ON      = 'gateways_on';
    private const K_DEFAULT = 'gateway_default';

    /** انتخاب مدیر — یک بار در هر درخواست خوانده می‌شود */
    private static ?array $prefs = null;

    public function __construct(private IranKish $irankish, private Zarinpal $zarinpal) {}

    /**
     * وضعیت فنی هر درگاه برای پنل مدیر.
     * @return array<string, array{label:string, ready:bool, why:?string}>
     */
    public function readiness(): array
    {
        $ikEnv  = (bool) config('gateways.irankish.enabled', true);
        $ikCred = $this->irankish->fake() || trim((string) config('irankish.terminal_id', '')) !== '';
        $zpEnv  = (bool) config('gateways.zarinpal.enabled', true);
        $zpCred = trim((string) config('gateways.zarinpal.merchant_id', '')) !== '';

        return [
            'irankish' => [
                'label' => self::LABELS['irankish'],
                'ready' => $ikEnv && $ikCred,
                'why'   => !$ikCred ? 'تنظیم نشده' : (!$ikEnv ? "خاموش در \u{2066}.env\u{2069}" : null),
            ],
            'zarinpal' => [
                'label' => self::LABELS['zarinpal'],
                'ready' => $zpEnv && $zpCred,
                'why'   => !$zpCred ? 'تنظیم نشده' : (!$zpEnv ? "خاموش در \u{2066}.env\u{2069}" : null),
            ],
        ];
    }

    /** انتخاب مدیر: ['on' => [...], 'default' => ?string] */
    public function prefs(): array
    {
        if (self::$prefs !== null) return self::$prefs;

        try {
            $rows = DB::table('zaban_meta')->whereIn('k', [self::K_ON, self::K_DEFAULT])->pluck('v', 'k');
        } catch (\Throwable $e) {
            $rows = collect();
        }

        $on = $rows->has(self::K_ON)
            ? array_values(array_intersect(self::SELECTABLE, explode(',', (string) $rows[self::K_ON])))
            : self::SELECTABLE;                                  /* هنوز ذخیره نشده: همه */
        $def = in_array($rows[self::K_DEFAULT] ?? null, self::SELECTABLE, true) ? $rows[self::K_DEFAULT] : null;

        return self::$prefs = ['on' => $on, 'default' => $def];
    }

    public function savePrefs(array $on, ?string $default): void
    {
        $on = array_values(array_intersect(self::SELECTABLE, $on));
        if (!in_array($default, $on, true)) $default = $on[0] ?? null;

        DB::table('zaban_meta')->updateOrInsert(['k' => self::K_ON], ['v' => implode(',', $on), 'updated_at' => now()]);
        DB::table('zaban_meta')->updateOrInsert(['k' => self::K_DEFAULT], ['v' => (string) $default, 'updated_at' => now()]);
        self::$prefs = null;
    }

    /**
     * درگاه‌هایی که دانشجو می‌بیند (آماده و روشن)، درگاه پیش‌فرض اول:
     * ['zarinpal' => 'زرین‌پال', 'irankish' => 'ایران کیش']
     */
    public function enabled(): array
    {
        $ready = $this->readiness();
        $p = $this->prefs();

        $out = [];
        foreach (self::SELECTABLE as $k) {
            if ($ready[$k]['ready'] && in_array($k, $p['on'], true)) $out[$k] = self::LABELS[$k];
        }
        if ($p['default'] && isset($out[$p['default']])) {
            $out = [$p['default'] => $out[$p['default']]] + $out;
        }
        return $out;
    }

    /** درگاه انتخابی کاربر اگر روشن است، وگرنه پیش‌فرض؛ null یعنی هیچ درگاهی نیست */
    public function pick(?string $wanted): ?string
    {
        $on = $this->enabled();
        if ($wanted !== null && isset($on[$wanted])) return $wanted;
        return array_key_first($on);
    }

    /** درگاه دیگرِ روشن (برای «… با درگاه X پرداخت کنید») */
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
