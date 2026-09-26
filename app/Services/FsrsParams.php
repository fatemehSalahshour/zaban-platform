<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * انتخاب وزن‌های FSRS برای یک کارت.
 *
 * چهار سطح، از خاص به عام. اولین سطحی که ردیف پذیرفته‌شده داشته باشد برنده است:
 *
 *   ۱. همین کاربر، همین رشته
 *   ۲. همین کاربر، کلی
 *   ۳. همه‌ی کاربران، همین رشته
 *   ۴. همه‌ی کاربران، کلی
 *   وگرنه وزن‌های پیش‌فرض FSRS-6
 *
 * چرا «پذیرفته‌شده»: بهینه‌ساز هر بار ردیف می‌نویسد، ولی وزنی که روی کارت‌های
 * کنارگذاشته بهتر از پیش‌فرض نبوده accepted=0 می‌ماند و اینجا نادیده گرفته
 * می‌شود. یعنی بدترین حالتِ ممکن، برگشتن به همان پیش‌فرض است.
 *
 * کش درون‌درخواستی: در یک جلسه‌ی مرور، ده‌ها کارت پشت سر هم حساب می‌شوند و
 * بدون کش هر کدام یک کوئری می‌شد.
 */
class FsrsParams
{
    /** @var array<string, array<int,float>|null> */
    private array $cache = [];

    /** @var array<int, string>|null نگاشت شناسه‌ی سؤال به کد رشته */
    private ?array $examOfQuestion = null;

    /**
     * وزن‌های مناسب برای این کاربر و رشته.
     *
     * @param  string|null $exam کد رشته (ce|it|cs) یا null برای «کلی»
     * @return array<int,float>
     */
    public function weights(int $userId, ?string $exam = null): array
    {
        $exam = in_array($exam, ['ce', 'it', 'cs'], true) ? $exam : '';
        $key  = $userId . '|' . $exam;

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key] ?? Fsrs::DEFAULT_W;
        }

        /* همه‌ی سطح‌های ممکن را یک‌جا می‌خوانیم تا چهار کوئری نشود */
        $scopes = array_values(array_unique(array_filter([
            $exam !== '' ? $userId . '|' . $exam : null,
            $userId . '|',
            $exam !== '' ? '0|' . $exam : null,
            '0|',
        ])));

        $rows = DB::table('zaban_fsrs_params')
            ->where('accepted', true)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('user_id', 0);
            })
            ->get(['user_id', 'exam', 'w'])
            ->keyBy(fn ($r) => $r->user_id . '|' . $r->exam);

        $found = null;
        foreach ($scopes as $s) {
            if (isset($rows[$s])) { $found = $rows[$s]; break; }
        }

        $w = null;
        if ($found) {
            $decoded = json_decode((string) $found->w, true);
            /* وزن خراب نباید کل مرور را بخواباند — برمی‌گردیم به پیش‌فرض */
            if (is_array($decoded) && count($decoded) === count(Fsrs::DEFAULT_W)) {
                $w = array_map('floatval', array_values($decoded));
            }
        }

        $this->cache[$key] = $w;
        return $w ?? Fsrs::DEFAULT_W;
    }

    /**
     * یک نمونه‌ی Fsrs با وزن‌های همین کاربر و رشته.
     */
    public function engine(int $userId, ?string $exam = null): Fsrs
    {
        return new Fsrs($this->weights($userId, $exam));
    }

    /**
     * رشته‌ی یک کارت.
     *
     * سؤال‌ها رشته‌ی مشخصی دارند. کلمه‌ها نه — یک کلمه می‌تواند در چند رشته
     * آمده باشد، پس برایشان از تنظیم «کلی» کاربر استفاده می‌شود.
     */
    public function examOf(string $itemType, int $itemId): ?string
    {
        if ($itemType !== 'question') return null;

        if ($this->examOfQuestion === null) $this->examOfQuestion = [];
        if (!array_key_exists($itemId, $this->examOfQuestion)) {
            $this->examOfQuestion[$itemId] = DB::table('questions')
                ->where('id', $itemId)->value('exam');
        }

        return $this->examOfQuestion[$itemId] ?: null;
    }
}
