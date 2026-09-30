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
    private const K_LAUNCH  = 'launch_off';      // درصد تخفیف رونمایی
    private const K_LAUNCH_FROM = 'launch_off_from';
    private const K_LAUNCH_TO   = 'launch_off_to';
    private const K_LAUNCH_TITLE = 'launch_off_title';   /* نام تخفیف روی صفحه‌ها */
    private const K_LAUNCH_NOTE  = 'launch_off_note';    /* یک خط توضیح، اختیاری */

    /** اگر مدیر چیزی ننوشته باشد */
    public const LAUNCH_TITLE_DEFAULT = 'تخفیف رونمایی';

    /**
     * تخفیف رونمایی — درصدی، مدت‌دار، روی مبلغ نهایی.
     *
     * روی «مبلغ قابل پرداخت» اعمال می‌شود نه روی قیمت تک‌رشته، یعنی روی
     * تخفیف پلکانی سوار می‌شود نه جایگزینش. کسی که هر سه رشته را می‌خرد،
     * هم تخفیف پلکانی می‌گیرد هم این را.
     *
     * بازه با تاریخ‌های میلادی در zaban_meta نگه داشته می‌شود. بیرون از
     * بازه، انگار اصلاً نیست — نه در نمایش، نه در مبلغ سفارش.
     *
     * @return array{percent:int, from:?string, to:?string, active:bool, days_left:?int}
     */
    public function launchOffer(): array
    {
        return Cache::remember('zaban.launch_off', 60, function () {
            $rows = DB::table('zaban_meta')
                ->whereIn('k', [self::K_LAUNCH, self::K_LAUNCH_FROM, self::K_LAUNCH_TO,
                                self::K_LAUNCH_TITLE, self::K_LAUNCH_NOTE])
                ->pluck('v', 'k');

            $pct  = (int) ($rows[self::K_LAUNCH] ?? 0);
            $pct  = max(0, min(90, $pct));          /* سقف ۹۰٪ — جلوی صفر شدن سهوی مبلغ */
            $from = trim((string) ($rows[self::K_LAUNCH_FROM] ?? '')) ?: null;
            $to   = trim((string) ($rows[self::K_LAUNCH_TO] ?? '')) ?: null;

            $now    = now();
            $active = $pct > 0
                && (!$from || $now->gte(\Illuminate\Support\Carbon::parse($from)))
                && (!$to   || $now->lte(\Illuminate\Support\Carbon::parse($to)));

            $left = null;
            if ($active && $to) {
                /* روزهای تقویمی تا روز پایان، نه کسر روز.
                   قبلاً کسری رو به بالا گرد می‌شد: بعدازظهر ۶ مهر با پایانِ
                   ۲۰ مهر «۱۵ روز» نشان می‌داد، در حالی که ۱۴ روز است. حالا
                   ۲۰ مهر یعنی ۱۴، و خودِ روز آخر صفر (= «امروز آخرین روز»). */
                $left = max(0, $now->copy()->startOfDay()
                    ->diffInDays(\Illuminate\Support\Carbon::parse($to)->startOfDay(), false));
            }

            /* متن از پنل می‌آید تا برای هر مناسبتی («تخفیف نوروزی»، «تخفیف
               پایان ترم») لازم نباشد کد عوض شود. */
            $rawTitle = trim((string) ($rows[self::K_LAUNCH_TITLE] ?? ''));
            $title = $rawTitle ?: self::LAUNCH_TITLE_DEFAULT;
            $note  = trim((string) ($rows[self::K_LAUNCH_NOTE] ?? '')) ?: null;

            return ['percent' => $pct, 'from' => $from, 'to' => $to,
                    'active' => $active, 'days_left' => $left,
                    /* title همان چیزی است که روی صفحه‌ها نوشته می‌شود؛ title_raw
                       دقیقاً چیزی است که مدیر ذخیره کرده (شاید خالی) — فرم پنل
                       باید همان را نشان دهد، نه مقدار جایگزین‌شده. */
                    'title' => $title, 'title_raw' => $rawTitle, 'note' => $note];
        });
    }

    public function saveLaunchOffer(int $percent, ?string $from, ?string $to,
                                    ?string $title = null, ?string $note = null): void
    {
        foreach ([self::K_LAUNCH => (string) max(0, min(90, $percent)),
                  self::K_LAUNCH_FROM => (string) $from,
                  self::K_LAUNCH_TO   => (string) $to,
                  self::K_LAUNCH_TITLE => trim((string) $title),
                  self::K_LAUNCH_NOTE  => trim((string) $note)] as $k => $v) {
            DB::table('zaban_meta')->updateOrInsert(['k' => $k],
                ['v' => $v, 'updated_at' => now()]);
        }
        Cache::forget('zaban.launch_off');
    }

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

        /* رشته‌هایی که از قبل دارد — مبنای «اعتبار خرید قبلی» */
        $ownedAll = count(array_unique(array_intersect($owned, Entitlements::EXAMS)));

        $n = count($billable);
        if ($n === 0) {
            return ['exams' => $exams, 'billable' => [], 'already_owned' => $already,
                    'list_price' => 0, 'discount' => 0, 'payable' => 0,
                    'price_version' => $this->version()];
        }

        $list  = $n * $unit;
        $stair = $bundle[$n] ?? $list;           /* پلکان عادی روی همین سفارش */

        /* اعتبار خرید قبلی.
           بدون این، کسی که اول یک رشته خریده و بعد دو تای دیگر را می‌خواهد،
           ۱٬۳۵۰٬۰۰۰ می‌داد؛ یعنی در مجموع ۲٬۵۵۰٬۰۰۰ در برابر ۱٬۵۰۰٬۰۰۰ کسی
           که همان روز اول هر سه را خریده بود. عملاً جریمه‌ی تصمیم مرحله‌به‌مرحله.

           حالا مبلغ = قیمت پکیجِ نهایی منهای قیمت پکیجی که قبلاً پرداخته، و
           هیچ‌وقت بیشتر از قیمت همین سفارش به‌تنهایی. یعنی هر مسیری که کاربر
           برود، در نهایت همان مبلغ پکیج کامل را می‌دهد، نه بیشتر. */
        $upgrade = 0;
        $bundled = $stair;
        if ($ownedAll > 0) {
            $after  = min($ownedAll + $n, 3);
            $credit = ($bundle[$after] ?? $list) - ($bundle[min($ownedAll, 3)] ?? 0);
            $bundled = max(0, min($stair, $credit));
            $upgrade = $stair - $bundled;
        }

        /* تخفیف رونمایی روی مبلغ بعد از تخفیف پلکانی می‌نشیند.
           رند به ۱۰۰۰ تومان پایین، تا مبلغ درگاه عدد گنگ نشود. */
        $off     = $this->launchOffer();
        $launch  = $off['active'] ? (int) (floor($bundled * $off['percent'] / 100 / 1000) * 1000) : 0;
        $payable = max(0, $bundled - $launch);

        return [
            'exams'         => $exams,
            'billable'      => $billable,
            'already_owned' => $already,
            'lines'         => array_map(fn ($e) => [
                'exam' => $e, 'name' => self::NAMES[$e], 'price' => $unit,
            ], $billable),
            'list_price'    => $list,
            /* «تخفیف» جمع هر دو است — همان عددی که کاربر صرفه‌جویی می‌کند */
            'discount'      => $list - $payable,
            'bundle_price'  => $bundled,
            /* تفکیک تخفیف، برای خط‌های خلاصه‌ی سفارش */
            'stair_off'     => $list - $stair,
            'upgrade_off'   => $upgrade,
            'owned_count'   => $ownedAll,
            'launch_off'    => $launch,
            'launch_pct'    => $off['active'] ? $off['percent'] : 0,
            'launch_title'  => $off['active'] ? $off['title'] : null,
            'launch_until'  => $off['active'] ? $off['to'] : null,
            'launch_days'   => $off['active'] ? $off['days_left'] : null,
            'payable'       => $payable,
            'price_version' => $this->version(),
        ];
    }

    /**
     * آمار نمایشی هر رشته برای کارت‌های صفحه‌ی خرید — چند سال، چند کلمه‌ی
     * یکتا، چند ظهور. از خودِ بانک کلمات حساب می‌شود، جایی دستی نوشته نشده
     * تا با هر همگام‌سازی محتوا خودش به‌روز بماند. کش ده‌دقیقه‌ای دارد چون
     * این عددها فقط با sync عوض می‌شوند، نه با هر بار باز شدن صفحه؛ سینک
     * پنل مدیریت خودش بعد از پایان کار همین کش را پاک می‌کند.
     *
     * @return array<string, array{years:int, words:int, occ:int, y1:?int, y2:?int}>
     */
    public function examStats(): array
    {
        return Cache::remember('zaban.exam_stats', 600, function () {
            $rows = DB::table('word_occurrences')
                ->selectRaw('exam, count(distinct year) as years, count(distinct word_id) as words, count(*) as occ, min(year) as y1, max(year) as y2')
                ->groupBy('exam')->get()->keyBy('exam');

            $out = [];
            foreach (Entitlements::EXAMS as $e) {
                $r = $rows[$e] ?? null;
                $out[$e] = [
                    'years' => (int) ($r->years ?? 0), 'words' => (int) ($r->words ?? 0),
                    'occ'   => (int) ($r->occ ?? 0),
                    'y1'    => isset($r->y1) ? (int) $r->y1 : null,
                    'y2'    => isset($r->y2) ? (int) $r->y2 : null,
                ];
            }
            return $out;
        });
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
