<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/**
 * سقف روزانه‌ی دیدن پاسخ و تشریحی (config zaban.answer_daily_cap).
 *
 * شمارش «سؤال متفاوت» در answer_reveals از ابتدای امروز است؛ سؤالی که امروز یک
 * بار دیده شده دوباره شمرده نمی‌شود. همه‌ی راه‌های رسیدن به پاسخ از همین
 * می‌گذرند: پاسخ تکی، پاسخ دسته‌ای، و کارنامه‌ی آزمون (پایان یا تاریخچه).
 * بدون کارنامه، ساختن و بستن ۴۹ آزمون پشت‌سرهم کل پاسخ‌نامه را می‌داد.
 */
class AnswerQuota
{
    public function __construct(private Alerts $alerts, private Settings $settings) {}

    public function cap(): int
    {
        return max(1, $this->settings->get('answer_daily_cap'));
    }

    /**
     * @param  int[] $qids
     * @return array{allowed:int[], fresh:int[], limited:int[]}
     *         allowed: می‌شود نشان داد · fresh: از allowed، آن‌هایی که امروز تازه‌اند
     *         (باید در answer_reveals ثبت شوند) · limited: به سقف خورد
     */
    public function allow(int $userId, array $qids): array
    {
        $qids = array_values(array_unique(array_map('intval', $qids)));
        if (!$qids) return ['allowed' => [], 'fresh' => [], 'limited' => []];

        $today = DB::table('answer_reveals')->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct()->pluck('question_id')->map(fn ($x) => (int) $x)->all();
        $seen  = array_flip($today);
        $room  = max(0, $this->cap() - count($today));

        $allowed = []; $fresh = []; $limited = [];
        foreach ($qids as $q) {
            if (isset($seen[$q]))      { $allowed[] = $q; }
            elseif ($room > 0)         { $allowed[] = $q; $fresh[] = $q; $room--; }
            else                       { $limited[] = $q; }
        }

        if ($limited) {
            $this->alerts->raise($userId, 'answer_cap',
                'به سقف ' . $this->cap() . ' سؤال در روز رسید (' . count($today) . ' سؤال دیده بود).');
        }
        return ['allowed' => $allowed, 'fresh' => $fresh, 'limited' => $limited];
    }

    public function message(): string
    {
        return 'سقف روزانه‌ی دیدن پاسخ (' . $this->cap() . ' سؤال) پر شده است؛ فردا دوباره در دسترس است.';
    }
}
