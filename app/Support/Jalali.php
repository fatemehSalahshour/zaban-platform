<?php

namespace App\Support;

/**
 * تبدیل تاریخ شمسی ↔ میلادی (الگوریتم jdf).
 * آزموده با: ۱۴۰۵/۰۶/۳۱ = 2026-09-22، ۱۴۰۶/۰۲/۱۶ = 2027-05-06،
 * و اسفندهای کبیسه‌ی ۱۳۹۹/۱۲/۳۰ و ۱۴۰۳/۱۲/۳۰ — در هر دو جهت.
 * همه‌ی تقسیم‌ها intdiv اند؛ «/» در PHP عدد اعشاری می‌دهد.
 */
final class Jalali
{
    /** @return array{0:int,1:int,2:int} [سال, ماه, روز] میلادی */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy  += 1595;
        $days = -355668 + (365 * $jy) + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd;
        $days += $jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186;

        $gy   = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $days--;
            $gy  += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy   += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy  += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd   = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $sal  = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm   = 0;
        while ($gm < 13 && $gd > $sal[$gm]) {
            $gd -= $sal[$gm];
            $gm++;
        }
        return [$gy, $gm, $gd];
    }

    /** @return array{0:int,1:int,2:int} [سال, ماه, روز] شمسی */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm  = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2  = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
              + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];

        $jy   = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy   += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy  += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + $days % 31;
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + ($days - 186) % 30;
        }
        return [$jy, $jm, $jd];
    }

    /**
     * «۱۴۰۶/۰۲/۱۶» یا «1406-02-16 23:59» (رقم فارسی یا لاتین، / یا -) ← «2027-05-06 23:59:00».
     * بدون ساعت: پایان همان روز (۲۳:۵۹:۵۹). null اگر قالب یا تاریخ نادرست باشد.
     */
    public static function parseToGregorian(string $in): ?string
    {
        $in = strtr(trim($in), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        if (!preg_match('~^(\d{4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$~', $in, $m)) return null;

        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($jy < 1300 || $jy > 1500 || $jm < 1 || $jm > 12 || $jd < 1 || $jd > ($jm <= 6 ? 31 : 30)) return null;

        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);
        /* ۳۰ اسفندِ سال غیرکبیسه به ۱ فروردین می‌رود — رد می‌شود */
        if (self::fromGregorian($gy, $gm, $gd) !== [$jy, $jm, $jd]) return null;

        $h = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 23;
        $i = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 59;
        $s = isset($m[6]) && $m[6] !== '' ? (int) $m[6] : (isset($m[4]) && $m[4] !== '' ? 0 : 59);
        if ($h > 23 || $i > 59 || $s > 59) return null;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $gy, $gm, $gd, $h, $i, $s);
    }

    /** «2027-05-06 23:59:59» ← «1406-02-16 23:59» برای نمایش در فرم مدیر */
    public static function formatFromGregorian(?string $g): ?string
    {
        if (!$g || !preg_match('~^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?~', $g, $m)) return null;
        [$jy, $jm, $jd] = self::fromGregorian((int) $m[1], (int) $m[2], (int) $m[3]);
        return sprintf('%04d-%02d-%02d', $jy, $jm, $jd) . (isset($m[4]) ? " {$m[4]}:{$m[5]}" : '');
    }
}
