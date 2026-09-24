<?php

namespace App\Services;

/**
 * FSRS-6 — جایگزین SM-2.
 *
 * تفاوت بنیادی با SM-2:
 *   SM-2 یک عدد نگه می‌دارد (ease factor) و بازه را در آن ضرب می‌کند.
 *   FSRS سه چیز را جدا مدل می‌کند:
 *     • پایداری (S) — چند روز طول می‌کشد تا احتمال یادآوری به ۹۰٪ برسد
 *     • دشواری (D) — عددی بین ۱ و ۱۰، ذاتیِ خود کارت
 *     • بازیابی‌پذیری (R) — احتمال اینکه همین حالا یادتان بیاید
 *   بعد بازه را طوری می‌چیند که R دقیقاً به عدد هدف شما برسد.
 *
 * پارامترها از FSRS4Anki v6.1.1 گرفته شده‌اند (۲۱ وزن پیش‌فرض).
 * این‌ها روی میلیون‌ها مرور واقعی آموزش دیده‌اند، ولی مخصوص کاربران ما نیستند.
 * وقتی review_logs به اندازه‌ی کافی بزرگ شد، می‌شود با optimizer وزن‌های
 * مخصوص پلتفرم را ساخت — تا آن موقع همین‌ها از SM-2 بهترند.
 *
 * منبع: https://github.com/open-spaced-repetition/fsrs4anki
 * مجوز: MIT
 */
class Fsrs
{
    /** وزن‌های پیش‌فرض FSRS-6 */
    public const DEFAULT_W = [
        0.212, 1.2931, 2.3065, 8.2956, 6.4133, 0.8334, 3.0194, 0.001,
        1.8722, 0.1666, 0.796, 1.4835, 0.0614, 0.2629, 1.6483, 0.6014,
        1.8729, 0.5425, 0.0912, 0.0658, 0.1542,
    ];

    /** درجه‌ها: ۱ یادم نبود · ۲ سخت بود · ۳ یادم بود · ۴ ساده بود */
    public const AGAIN = 1, HARD = 2, GOOD = 3, EASY = 4;

    private array $w;
    private float $decay;
    private float $factor;

    public function __construct(
        ?array $w = null,
        private float $requestRetention = 0.90,   // هدف: ۹۰٪ احتمال یادآوری سر موعد
        private int $maximumInterval = 3650,      // ده سال؛ برای کنکور عملاً بی‌اثر
    ) {
        $this->w = $w ?: self::DEFAULT_W;
        $this->decay  = -$this->w[20];
        $this->factor = pow(0.9, 1 / $this->decay) - 1;
    }

    /* ================= ورودی اصلی ================= */

    /**
     * یک مرور را اعمال می‌کند.
     *
     * @param  array|null $card ['stability'=>float,'difficulty'=>float,'due_date'=>string,'last_review_at'=>string]
     *                          null یعنی کارت تازه
     * @param  int $rating ۱ تا ۴
     * @param  int $elapsedDays چند روز از مرور قبلی گذشته (برای کارت تازه ۰)
     */
    public function review(?array $card, int $rating, int $elapsedDays = 0): array
    {
        $rating = max(1, min(4, $rating));

        if (!$card || ($card['stability'] ?? null) === null) {
            $s = $this->initStability($rating);
            $d = $this->initDifficulty($rating);
        } else {
            $lastS = (float) $card['stability'];
            $lastD = (float) $card['difficulty'];
            $r     = $this->retrievability($elapsedDays, $lastS);

            $d = $this->nextDifficulty($lastD, $rating);

            if ($elapsedDays < 1) {
                // مرور دوباره در همان روز — بازه عوض نمی‌شود، فقط پایداری کمی جابه‌جا
                $s = $this->shortTermStability($lastS, $rating);
            } elseif ($rating === self::AGAIN) {
                $s = $this->forgetStability($lastD, $lastS, $r);
            } else {
                $s = $this->recallStability($lastD, $lastS, $r, $rating);
            }
        }

        $interval = $this->nextInterval($s);

        return [
            'stability'     => round($s, 4),
            'difficulty'    => round($d, 4),
            'interval_days' => $interval,
            'due_date'      => now()->addDays($interval)->toDateString(),
            'retrievability_at_due' => $this->requestRetention,
        ];
    }

    /* ================= فرمول‌ها ================= */

    /** منحنی فراموشی: احتمال یادآوری بعد از t روز */
    public function retrievability(int $elapsedDays, float $stability): float
    {
        if ($stability <= 0) return 0.0;
        return pow(1 + $this->factor * $elapsedDays / $stability, $this->decay);
    }

    /** بازه‌ای که در آن R به عدد هدف می‌رسد */
    private function nextInterval(float $stability): int
    {
        $ivl = $stability / $this->factor
             * (pow($this->requestRetention, 1 / $this->decay) - 1);
        return (int) max(1, min($this->maximumInterval, (int) round($ivl)));
    }

    private function initStability(int $rating): float
    {
        return max($this->w[$rating - 1], 0.1);
    }

    private function initDifficulty(int $rating): float
    {
        return $this->constrainD($this->w[4] - exp($this->w[5] * ($rating - 1)) + 1);
    }

    /**
     * دشواری تازه.
     * linear_damping باعث می‌شود کارت‌های از قبل دشوار، با هر لغزش
     * کمتر دشوارتر شوند — وگرنه دشواری زود به سقف می‌چسبد.
     */
    private function nextDifficulty(float $d, int $rating): float
    {
        $deltaD = -$this->w[6] * ($rating - 3);
        $nextD  = $d + $deltaD * (10 - $d) / 9;          // linear damping
        // بازگشت به میانگین: دشواری آرام‌آرام به سمت مقدار اولیه‌ی «ساده» کشیده می‌شود
        $nextD  = $this->w[7] * $this->initDifficulty(self::EASY) + (1 - $this->w[7]) * $nextD;
        return $this->constrainD($nextD);
    }

    private function constrainD(float $d): float
    {
        return min(max(round($d, 2), 1.0), 10.0);
    }

    /** پایداری بعد از یادآوری موفق */
    private function recallStability(float $d, float $s, float $r, int $rating): float
    {
        $hardPenalty = $rating === self::HARD ? $this->w[15] : 1.0;
        $easyBonus   = $rating === self::EASY ? $this->w[16] : 1.0;

        return $s * (1 + exp($this->w[8])
            * (11 - $d)
            * pow($s, -$this->w[9])
            * (exp((1 - $r) * $this->w[10]) - 1)
            * $hardPenalty
            * $easyBonus);
    }

    /** پایداری بعد از فراموشی — همیشه از پایداری قبلی کمتر است */
    private function forgetStability(float $d, float $s, float $r): float
    {
        $sMin = $s / exp($this->w[17] * $this->w[18]);
        $val  = $this->w[11]
              * pow($d, -$this->w[12])
              * (pow($s + 1, $this->w[13]) - 1)
              * exp((1 - $r) * $this->w[14]);
        return min($val, $sMin);
    }

    /** مرور دوباره در همان روز */
    private function shortTermStability(float $s, int $rating): float
    {
        $sinc = exp($this->w[17] * ($rating - 3 + $this->w[18])) * pow($s, -$this->w[19]);
        if ($rating >= self::GOOD) $sinc = max($sinc, 1.0);
        return $s * $sinc;
    }

    /* ================= کمکی برای رابط ================= */

    /**
     * بازه‌ای که هر درجه می‌سازد — برای نشان دادن روی چهار دکمه‌ی مرور،
     * قبل از اینکه کاربر انتخاب کند.
     */
    public function preview(?array $card, int $elapsedDays = 0): array
    {
        $out = [];
        foreach ([self::AGAIN, self::HARD, self::GOOD, self::EASY] as $r) {
            $out[$r] = $this->review($card, $r, $elapsedDays)['interval_days'];
        }
        // «ساده» همیشه باید از «یادم بود» دورتر باشد
        $out[self::EASY] = max($out[self::EASY], $out[self::GOOD] + 1);
        return $out;
    }

    /**
     * تبدیل کارت‌های SM-2 موجود به FSRS.
     * بازه‌ی فعلی می‌شود پایداری، و از ease factor دشواری بازسازی می‌شود.
     * دقیق نیست ولی از صفر شروع کردن خیلی بهتر است.
     */
    public function fromSm2(float $easeFactor, int $intervalDays): array
    {
        $s = max($intervalDays, 0.1);
        $d = $this->constrainD(
            11 - ($easeFactor - 1) / (exp($this->w[8]) * pow($s, -$this->w[9]) * (exp(0.1 * $this->w[10]) - 1))
        );
        return ['stability' => round($s, 4), 'difficulty' => $d];
    }

    /** «مسلط» در FSRS یعنی بازه‌ی بلند و دشواری پایین، نه شمارش تکرار. */
    public function isMastered(float $stability, float $difficulty, int $intervalDays): bool
    {
        return $intervalDays >= 21 && $difficulty <= 6.0;
    }
}
