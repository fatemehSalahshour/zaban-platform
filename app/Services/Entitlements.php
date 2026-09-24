<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تنها مرجع «این کاربر به کدام رشته دسترسی دارد».
 *
 * قاعده‌ی سفت: هیچ کنترلری مستقیم به جدول zaban_entitlements دست نزند.
 * هر جای دیگری که بخواهد خودش تصمیم بگیرد، همان‌جا سوراخ امنیتی است.
 */
class Entitlements
{
    public const EXAMS = ['ce', 'it', 'cs'];

    private const TTL = 300;   // پنج دقیقه؛ لغو دسترسی حداکثر با همین تأخیر اثر می‌کند

    /** رشته‌های فعال کاربر — ['ce','it'] */
    public function for(int $userId): array
    {
        return Cache::remember("zaban.ent.$userId", self::TTL, function () use ($userId) {
            return DB::table('zaban_entitlements')
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('exam')
                ->all();
        });
    }

    public function has(int $userId, string $exam): bool
    {
        return in_array($exam, $this->for($userId), true);
    }

    /** بعد از هر تغییر دسترسی حتماً صدا زده شود، وگرنه کاربر تا پنج دقیقه معطل می‌ماند. */
    public function forget(int $userId): void
    {
        Cache::forget("zaban.ent.$userId");
    }

    /**
     * اعطای دسترسی. idempotent: اگر ردیف هست فقط انقضا را جلو می‌برد،
     * پس دو بار صدا شدن callback درگاه دسترسی دوتایی نمی‌سازد.
     */
    public function grant(int $userId, string $exam, ?string $expiresAt,
                          string $source = 'purchase', ?int $orderId = null, ?string $note = null): void
    {
        if (!in_array($exam, self::EXAMS, true)) {
            throw new \InvalidArgumentException("رشته‌ی نامعتبر: $exam");
        }

        $existing = DB::table('zaban_entitlements')
            ->where(['user_id' => $userId, 'exam' => $exam])->first();

        if ($existing) {
            // انقضای دورتر برنده است؛ خرید دوباره دسترسی را کوتاه نمی‌کند.
            $keep = $existing->expires_at === null || $expiresAt === null
                ? null
                : max($existing->expires_at, $expiresAt);

            DB::table('zaban_entitlements')
                ->where(['user_id' => $userId, 'exam' => $exam])
                ->update([
                    'expires_at' => $keep,
                    'revoked_at' => null,
                    'order_id'   => $orderId ?: $existing->order_id,
                    'source'     => $existing->source === 'staff' ? 'staff' : $source,
                ]);
        } else {
            DB::table('zaban_entitlements')->insert([
                'user_id' => $userId, 'exam' => $exam, 'source' => $source,
                'order_id' => $orderId, 'granted_at' => now(),
                'expires_at' => $expiresAt, 'note' => $note,
            ]);
        }

        $this->forget($userId);
    }

    /** لغو — ردیف پاک نمی‌شود تا تاریخچه‌ی برگشت وجه بماند. */
    public function revoke(int $userId, string $exam, ?string $note = null): void
    {
        DB::table('zaban_entitlements')
            ->where(['user_id' => $userId, 'exam' => $exam])
            ->update(['revoked_at' => now(), 'note' => $note]);

        $this->forget($userId);
    }

    /**
     * رشته‌ی پیش‌فرضی که کاربر باید ببیند.
     * اگر رشته‌ی پروفایلش را نخریده، اولین رشته‌ی خریداری‌شده را می‌دهیم
     * تا داشبورد به‌جای پی‌وال خالی، چیزی برای نشان دادن داشته باشد.
     */
    /* =================================================================
     |  نسخه‌ی نمایشی (دمو)
     |  مدیر در پنل یک سال (مثلاً ۱۴۰۵) و رشته‌های پیش‌فرض را انتخاب می‌کند.
     |  کاربری که رشته‌ای را نخریده، محتوای همان یک سالِ آن رشته را دارد.
     |  رشته‌های دمو: اگر کاربر در پروفایل رشته‌ای انتخاب کرده که نخریده،
     |  فقط همان؛ وگرنه رشته‌های انتخاب‌شده‌ی مدیر. خرید یک رشته، دموی
     |  رشته‌های دیگر را برنمی‌دارد. محدودیت زمانی ندارد.
     * ================================================================= */

    public const K_DEMO_YEAR  = 'demo_year';
    public const K_DEMO_EXAMS = 'demo_exams';

    /** سال دمو یا null اگر خاموش است */
    public function demoYear(): ?int
    {
        $v = Cache::remember('zaban.demo.year', 60, fn () =>
            DB::table('zaban_meta')->where('k', self::K_DEMO_YEAR)->value('v'));
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    /** رشته‌هایی که مدیر برای دمو انتخاب کرده (وقتی کاربر رشته‌ای انتخاب نکرده) */
    public function demoExamsSetting(): array
    {
        $v = (string) Cache::remember('zaban.demo.exams', 60, fn () =>
            DB::table('zaban_meta')->where('k', self::K_DEMO_EXAMS)->value('v'));
        return array_values(array_intersect(array_filter(explode(',', $v)), self::EXAMS));
    }

    public function saveDemo(?int $year, array $exams): void
    {
        DB::table('zaban_meta')->updateOrInsert(['k' => self::K_DEMO_YEAR],
            ['v' => $year ? (string) $year : '', 'updated_at' => now()]);
        DB::table('zaban_meta')->updateOrInsert(['k' => self::K_DEMO_EXAMS],
            ['v' => implode(',', array_intersect($exams, self::EXAMS)), 'updated_at' => now()]);
        Cache::forget('zaban.demo.year');
        Cache::forget('zaban.demo.exams');
    }

    /** رشته‌هایی که این کاربر به‌صورت دمو دارد (هیچ‌کدام خریده‌شده نیست) */
    public function demoExams(int $userId): array
    {
        if (!$this->demoYear()) return [];
        $owned = $this->for($userId);
        $pref  = DB::table('zaban_profiles')->where('user_id', $userId)->value('exam');
        $list  = ($pref && in_array($pref, self::EXAMS, true) && !in_array($pref, $owned, true))
            ? [$pref]
            : $this->demoExamsSetting();
        return array_values(array_diff($list, $owned));
    }

    /**
     * دامنه‌ی دسترسی به یک رشته — تنها جایی که «خریده یا دمو» تصمیم گرفته می‌شود:
     *   null  = کامل (خریده)
     *   int   = فقط همین سال (دمو)
     *   false = هیچ
     */
    public function scope(int $userId, string $exam): int|null|false
    {
        if ($this->has($userId, $exam)) return null;
        $y = $this->demoYear();
        return ($y && in_array($exam, $this->demoExams($userId), true)) ? $y : false;
    }

    /** آیا این کاربر به یک سؤال/کاربرد مشخص (رشته + سال) دسترسی دارد؟ */
    public function canItem(int $userId, string $exam, int $year): bool
    {
        $s = $this->scope($userId, $exam);
        return $s === null || ($s !== false && $s === $year);
    }

    /**
     * محدود کردن یک کوئری روی جدولی با ستون‌های exam و year به آنچه کاربر
     * می‌تواند ببیند. اگر هیچ دسترسی‌ای نباشد، شرطی می‌گذارد که هیچ ردیفی نیاید.
     */
    public function scopeQuery($query, int $userId, string $examCol = 'exam', string $yearCol = 'year')
    {
        $owned = $this->for($userId);
        $demo  = $this->demoExams($userId);
        $y     = $this->demoYear();

        return $query->where(function ($w) use ($owned, $demo, $y, $examCol, $yearCol) {
            $w->whereRaw('1 = 0');
            if ($owned) $w->orWhereIn($examCol, $owned);
            if ($demo && $y) $w->orWhere(fn ($d) => $d->whereIn($examCol, $demo)->where($yearCol, $y));
        });
    }

    public function defaultExam(int $userId, ?string $preferred = null): ?string
    {
        $owned = $this->for($userId);
        if (!$owned) return null;
        return ($preferred && in_array($preferred, $owned, true)) ? $preferred : $owned[0];
    }
}
