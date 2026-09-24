<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * قیمت‌گذاری — فقط سمت سرور.
 *
 * مبلغ نهایی هرگز از مرورگر نمی‌آید. مرورگر فقط می‌گوید «کدام رشته‌ها»؛
 * مبلغ اینجا حساب می‌شود.
 *
 * تغییر نسبت به نسخه‌ی قبل: قیمت‌ها از ثابت‌های PHP به zaban_meta منتقل
 * شدند تا مدیریت بتواند بدون دیپلوی عوضشان کند. کش یک دقیقه‌ای هست تا
 * هر بار quote یک کوئری اضافه نزند، و بعد از هر ذخیره پاک می‌شود.
 */
class Pricing
{
    /** کلیدهای zaban_meta */
    private const K_BUNDLE  = 'price_bundle_';   // + تعداد رشته
    private const K_VERSION = 'price_version';

    /** اگر هیچ‌وقت در پنل ذخیره نشده باشد (تومان) */
    private const DEFAULTS = [1 => 1_000_000, 2 => 1_200_000, 3 => 1_400_000];

    public const NAMES = ['ce' => 'مهندسی کامپیوتر', 'it' => 'آی‌تی', 'cs' => 'علوم کامپیوتر'];

    /* ================= خواندن ================= */

    /** @return array{1:int,2:int,3:int} */
    public function bundles(): array
    {
        return Cache::remember('zaban.prices', 60, function () {
            $rows = DB::table('zaban_meta')
                ->where('k', 'like', self::K_BUNDLE . '%')
                ->pluck('v', 'k');

            $out = [];
            foreach (self::DEFAULTS as $n => $default) {
                $v = $rows[self::K_BUNDLE . $n] ?? null;
                $out[$n] = $v !== null && is_numeric($v) ? (int) $v : $default;
            }
            return $out;
        });
    }

    /**
     * نسخه‌ی قیمت روی هر سفارش ثبت می‌شود. اگر مدیر قیمت را عوض کند و
     * کاربری با قیمت قدیمی نصفه‌کاره مانده باشد، از روی همین می‌فهمیم.
     */
    public function version(): string
    {
        return (string) (DB::table('zaban_meta')->where('k', self::K_VERSION)->value('v') ?: '1');
    }

    /* ================= نوشتن ================= */

    /**
     * @param array{1:int,2:int,3:int} $bundles
     */
    public function saveBundles(array $bundles): void
    {
        foreach ([1, 2, 3] as $n) {
            if (!isset($bundles[$n])) continue;
            DB::table('zaban_meta')->updateOrInsert(
                ['k' => self::K_BUNDLE . $n],
                ['v' => (string) max(0, (int) $bundles[$n]), 'updated_at' => now()]
            );
        }

        /* نسخه یک شماره جلو می‌رود تا سفارش‌های قدیمی قابل تشخیص بمانند. */
        DB::table('zaban_meta')->updateOrInsert(
            ['k' => self::K_VERSION],
            ['v' => (string) ((int) $this->version() + 1), 'updated_at' => now()]
        );

        Cache::forget('zaban.prices');
    }

    /* ================= محاسبه ================= */

    /**
     * @param  string[] $exams رشته‌های درخواستی
     * @param  string[] $owned رشته‌هایی که کاربر از قبل دارد — دوباره پول نمی‌گیریم
     */
    public function quote(array $exams, array $owned = []): array
    {
        $bundle = $this->bundles();
        $unit   = $bundle[1];

        $exams = array_values(array_intersect(array_unique($exams), Entitlements::EXAMS));
        sort($exams);

        $already  = array_values(array_intersect($exams, $owned));
        $billable = array_values(array_diff($exams, $owned));
        sort($billable);

        $n = count($billable);
        if ($n === 0) {
            return ['exams' => $exams, 'billable' => [], 'already_owned' => $already,
                    'list_price' => 0, 'discount' => 0, 'payable' => 0,
                    'price_version' => $this->version()];
        }

        $list    = $n * $unit;
        $payable = $bundle[$n] ?? $list;

        return [
            'exams'         => $exams,
            'billable'      => $billable,
            'already_owned' => $already,
            'lines'         => array_map(fn ($e) => [
                'exam' => $e, 'name' => self::NAMES[$e], 'price' => $unit,
            ], $billable),
            'list_price'    => $list,
            'discount'      => $list - $payable,
            'payable'       => $payable,
            'price_version' => $this->version(),
        ];
    }

    /**
     * تاریخ پایان دسترسی — «تا روز برگزاری کنکور».
     * در zaban_meta با کلید exam_date نگه داشته می‌شود تا هر سال فقط
     * یک ردیف عوض شود، نه یک دیپلوی.
     */
    public function accessUntil(): ?string
    {
        $d = trim((string) DB::table('zaban_meta')->where('k', 'exam_date')->value('v'));
        if ($d === '') return null;

        /* همیشه میلادی ذخیره می‌شود. مقدار قدیمی شمسی (پیش از این نسخه راهنمای
           فرم شمسی بود و همان خام ذخیره می‌شد) اینجا تبدیل می‌شود — وگرنه
           expires_at سال ۱۴۰۶ میلادی می‌شد و هر خرید بلافاصله منقضی. */
        if (preg_match('~^(\d{4})~', strtr($d, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']), $m)
            && (int) $m[1] < 1700) {
            return \App\Support\Jalali::parseToGregorian($d);
        }
        return $d;
    }
}
