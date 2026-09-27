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
     |  نسخه‌ی آزمایشی «پهنا باز، عمق سهمیه‌ای»:
     |
     |  کاربری که رشته‌ای را نخریده، همه‌ی سال‌های آن رشته را می‌بیند — فهرست
     |  کلمه‌ها، نوار سال‌ها، جدول «کجا در کنکور آمده»، پوشش بانک و پیش‌بینی.
     |  یعنی قابلیت‌های پلتفرم را کامل لمس می‌کند.
     |
     |  آنچه محدود است «عمق» است نه «پهنا»:
     |    · معنی و مثال‌ها → تا سقف مشخصی کلمه‌ی متمایز (پیش‌فرض ۲۰۰)
     |    · پاسخ و تشریح و آزمون دادن → فقط روی دفترچه‌های آزمایشی مدیر
     |
     |  چرا این‌طور: نسخه‌ی قبلی یک سال را باز می‌گذاشت، ولی آن وقت نوار
     |  سال‌ها و جدول ظهور خالی می‌ماندند و کاربر فکر می‌کرد پلتفرم این
     |  قابلیت‌ها را ندارد — یعنی دمو دقیقاً همان چیزی را پنهان می‌کرد که
     |  باید می‌فروخت.
     |
     |  سقف بر «کلمه‌ی متمایز» است نه بر کلیک: کلمه‌ای که یک بار باز شده،
     |  هر بار دیگر رایگان است. وگرنه کاربر از کلیک کردن می‌ترسد.
     * ================================================================= */

    public const K_TRIAL_ON    = 'trial_on';
    public const K_TRIAL_CAP   = 'trial_word_cap';
    public const K_TRIAL_BOOKS = 'trial_booklets';   /* «1404:ce,1404:it» */

    /* کلیدهای قدیمی — فقط برای پاک کردن کش نسخه‌های پیشین */
    public const K_DEMO_YEAR  = 'demo_year';
    public const K_DEMO_EXAMS = 'demo_exams';

    /** نسخه‌ی آزمایشی روشن است؟ */
    public function trialOn(): bool
    {
        return (bool) Cache::remember('zaban.trial.on', 60, fn () =>
            DB::table('zaban_meta')->where('k', self::K_TRIAL_ON)->value('v'));
    }

    /** سقف کلمه‌ی متمایز در نسخه‌ی آزمایشی */
    public function trialCap(): int
    {
        $v = Cache::remember('zaban.trial.cap', 60, fn () =>
            DB::table('zaban_meta')->where('k', self::K_TRIAL_CAP)->value('v'));
        return max(0, (int) ($v ?: 200));
    }

    /**
     * دفترچه‌های آزمایشی: [['year' => 1404, 'exam' => 'ce'], …]
     * روی این‌ها کاربر می‌تواند آزمون کامل بدهد و پاسخ و تشریح ببیند.
     */
    public function trialBooklets(): array
    {
        $v = (string) Cache::remember('zaban.trial.books', 60, fn () =>
            DB::table('zaban_meta')->where('k', self::K_TRIAL_BOOKS)->value('v'));

        $out = [];
        foreach (array_filter(explode(',', $v)) as $pair) {
            [$y, $e] = array_pad(explode(':', trim($pair)), 2, null);
            if (is_numeric($y) && in_array($e, self::EXAMS, true)) {
                $out[] = ['year' => (int) $y, 'exam' => $e];
            }
        }
        return $out;
    }

    public function saveTrial(bool $on, int $cap, array $booklets): void
    {
        $pairs = [];
        foreach ($booklets as $b) {
            $y = (int) ($b['year'] ?? 0); $e = $b['exam'] ?? '';
            if ($y > 0 && in_array($e, self::EXAMS, true)) $pairs[] = $y . ':' . $e;
        }

        foreach ([
            self::K_TRIAL_ON    => $on ? '1' : '',
            self::K_TRIAL_CAP   => (string) max(0, $cap),
            self::K_TRIAL_BOOKS => implode(',', array_unique($pairs)),
        ] as $k => $v) {
            DB::table('zaban_meta')->updateOrInsert(['k' => $k], ['v' => $v, 'updated_at' => now()]);
        }

        foreach (['on', 'cap', 'books'] as $c) Cache::forget('zaban.trial.' . $c);
    }

    /** آیا این کاربر روی این رشته در حالت آزمایشی است؟ (یعنی نخریده) */
    public function isTrial(int $userId, string $exam): bool
    {
        return $this->trialOn() && !$this->has($userId, $exam);
    }

    /**
     * آیا می‌تواند پاسخ ببیند یا آزمون بدهد؟
     * خریدار همیشه؛ کاربر آزمایشی فقط روی دفترچه‌های آزمایشی.
     */
    public function canAnswer(int $userId, string $exam, int $year): bool
    {
        if ($this->has($userId, $exam)) return true;
        if (!$this->trialOn()) return false;

        foreach ($this->trialBooklets() as $b) {
            if ($b['exam'] === $exam && $b['year'] === $year) return true;
        }
        return false;
    }

    /** سال دمو — نسخه‌ی قدیمی، دیگر استفاده نمی‌شود */
    public function demoYear(): ?int
    {
        return null;
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
    /** رشته‌هایی که این کاربر نخریده و در حالت آزمایشی می‌بیند */
    public function demoExams(int $userId): array
    {
        if (!$this->trialOn()) return [];
        return array_values(array_diff(self::EXAMS, $this->for($userId)));
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

        /* آزمایشی: مرورِ همه‌ی سال‌ها باز است. محدودیت در «عمق» است و لایه‌ی
           سهمیه اعمالش می‌کند، نه اینجا. */
        return $this->trialOn() ? null : false;
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

        /* در حالت آزمایشی هیچ محدودیت سالی نیست — همه‌چیز دیده می‌شود و
           عمق را سهمیه کنترل می‌کند. */
        if ($this->trialOn()) return $query;

        return $query->where(function ($w) use ($owned, $examCol) {
            $w->whereRaw('1 = 0');
            if ($owned) $w->orWhereIn($examCol, $owned);
        });
    }

    public function defaultExam(int $userId, ?string $preferred = null): ?string
    {
        $owned = $this->for($userId);
        if (!$owned) return null;
        return ($preferred && in_array($preferred, $owned, true)) ? $preferred : $owned[0];
    }
}
