<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/**
 * سقف روزانه‌ی دیدن معنی کلمه (پنل یا config zaban.meaning_daily_cap).
 * همان منطق AnswerQuota: «کلمه‌ی متفاوت» از ابتدای امروز در meaning_reveals؛
 * کلمه‌ای که امروز یک بار گرفته شده دوباره شمرده نمی‌شود.
 */
class MeaningQuota
{
    public function __construct(private Alerts $alerts, private Settings $settings) {}

    public function cap(): int
    {
        return max(1, $this->settings->get('meaning_daily_cap'));
    }

    /** @return array{allowed:int[], fresh:int[], limited:int[]} */
    public function allow(int $userId, array $wordIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $wordIds)));
        if (!$ids) return ['allowed' => [], 'fresh' => [], 'limited' => []];

        $today = DB::table('meaning_reveals')->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct()->pluck('word_id')->map(fn ($x) => (int) $x)->all();
        $seen = array_flip($today);
        $room = max(0, $this->cap() - count($today));

        $allowed = []; $fresh = []; $limited = [];
        foreach ($ids as $w) {
            if (isset($seen[$w]))  { $allowed[] = $w; }
            elseif ($room > 0)     { $allowed[] = $w; $fresh[] = $w; $room--; }
            else                   { $limited[] = $w; }
        }

        if ($limited) {
            $this->alerts->raise($userId, 'meaning_cap',
                'به سقف ' . $this->cap() . ' کلمه در روز رسید (' . count($today) . ' کلمه گرفته بود).');
        }
        return ['allowed' => $allowed, 'fresh' => $fresh, 'limited' => $limited];
    }

    public function message(): string
    {
        return 'سقف روزانه‌ی دیدن معنی کلمه‌ها (' . $this->cap() . ' کلمه) پر شده است؛ فردا دوباره در دسترس است.';
    }
}
