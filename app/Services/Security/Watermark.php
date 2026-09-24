<?php

namespace App\Services\Security;

/**
 * امضای نامرئی به‌ازای کاربر (امنیت محتوا، قدم ۴).
 *
 * در هر معنی، مثال و پاسخ تشریحی که به کاربر می‌رود، شماره‌ی همان کاربر با
 * نویسه‌های بی‌پهنای یونیکد نوشته می‌شود — نه روی صفحه دیده می‌شود و نه در
 * کپی‌وچسباندن. اگر متنی جایی منتشر شد، پنل «ردیابی متن منتشرشده» از روی همین
 * می‌گوید از حساب چه کسی برداشته شده. امضا در «هر» تکه تکرار می‌شود، پس چند
 * معنیِ کپی‌شده هم کافی است.
 *
 * قالب: MARK + ۱۲ رقم مبنای ۴ (تا ۱۶٬۷۷۷٬۲۱۵ کاربر) + ۱ رقم کنترل + MARK
 * جای درج: بعد از اولین فاصله (یا ابتدای متن) — کنار فاصله، اتصال حروف فارسی
 * به‌هم نمی‌خورد.
 *
 * محدودیت (صادقانه): کسی که بداند می‌تواند این نویسه‌ها را پاک کند. این لایه برای
 * پیدا کردن منبع نشت است، نه جلوگیری از کپی.
 */
class Watermark
{
    private const MARK   = "\u{2064}";                                    // INVISIBLE PLUS
    private const DIGITS = ["\u{200B}", "\u{2060}", "\u{2062}", "\u{2063}"]; // ZWSP, WJ, INVISIBLE TIMES, INVISIBLE SEPARATOR
    private const LEN    = 12;

    public function encode(int $uid): string
    {
        $d = [];
        $n = max(0, $uid);
        for ($i = 0; $i < self::LEN; $i++) { $d[] = $n % 4; $n = intdiv($n, 4); }
        $d = array_reverse($d);
        $d[] = array_sum($d) % 4;                                           /* رقم کنترل */
        return self::MARK . implode('', array_map(fn ($x) => self::DIGITS[$x], $d)) . self::MARK;
    }

    public function mark(int $uid, ?string $text): ?string
    {
        if ($text === null || $text === '') return $text;
        $sig = $this->encode($uid);
        $pos = mb_strpos($text, ' ');
        return $pos === false ? $sig . $text : mb_substr($text, 0, $pos + 1) . $sig . mb_substr($text, $pos + 1);
    }

    /** همه‌ی امضاهای معتبر یک متن ← [شماره‌ی کاربر => تعداد تکرار] */
    public function decode(string $text): array
    {
        $digit = array_flip(self::DIGITS);
        $alt   = implode('|', array_map(fn ($c) => preg_quote($c, '/'), self::DIGITS));
        preg_match_all('/' . preg_quote(self::MARK, '/') . '((?:' . $alt . '){' . (self::LEN + 1) . '})' . preg_quote(self::MARK, '/') . '/u', $text, $m);

        $found = [];
        foreach ($m[1] as $payload) {
            $chars = preg_split('//u', $payload, -1, PREG_SPLIT_NO_EMPTY);
            $d = array_map(fn ($c) => $digit[$c], $chars);
            $check = array_pop($d);
            if (array_sum($d) % 4 !== $check) continue;                    /* خراب یا ساختگی */
            $uid = 0;
            foreach ($d as $x) $uid = $uid * 4 + $x;
            $found[$uid] = ($found[$uid] ?? 0) + 1;
        }
        arsort($found);
        return $found;
    }

    /** حذف امضا — برای مقایسه یا وقتی متن تمیز لازم است */
    public function strip(string $text): string
    {
        return str_replace(array_merge([self::MARK], self::DIGITS), '', $text);
    }
}
