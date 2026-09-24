<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * عددهای امنیت محتوا — از پنل مدیریت (zaban_meta، کلید sec_<نام>)، وگرنه config/zaban.php.
 * هر جای کد که یکی از این‌ها را می‌خواهد از اینجا می‌خواند، نه مستقیم از config.
 */
class Settings
{
    /** نام ← [برچسب فارسی برای پنل، کمینه، بیشینه] */
    public const FIELDS = [
        'answer_daily_cap'   => ['سقف روزانه‌ی دیدن پاسخ و تشریحی (سؤال)',          1, 5000],
        'word_daily_cap'     => ['سقف روزانه‌ی دیدن مثال‌ها (کلمه)',                  1, 10000],
        'meaning_daily_cap'  => ['سقف روزانه‌ی دیدن معنی (کلمه)',                     1, 20000],
        'lock_words_10min'   => ['قفل: مثال‌های بیش از این تعداد کلمه در ۱۰ دقیقه',     10, 10000],
        'lock_meanings_10min'=> ['قفل: معنی بیش از این تعداد کلمه در ۱۰ دقیقه',         10, 20000],
        'lock_answers_10min' => ['قفل: پاسخ بیش از این تعداد سؤال در ۱۰ دقیقه',          10, 5000],
        'lock_cap_streak'    => ['قفل: رسیدن به سقف در این تعداد روز پیاپی',             2, 30],
        'lock_hours'         => ['مدت قفل خودکار (ساعت)',                               1, 720],
    ];

    public function get(string $name): int
    {
        $meta = Cache::remember("zaban.sec.$name", 60, fn () =>
            DB::table('zaban_meta')->where('k', "sec_$name")->value('v'));
        return is_numeric($meta) ? (int) $meta : (int) config("zaban.$name");
    }

    /** سقف پیش‌فرض کارت تازه در روز (پنل مدیریت؛ پروفایل دانشجو بر آن مقدم است) */
    public function newPerDay(): int
    {
        $v = Cache::remember('zaban.sec.new_per_day', 60, fn () =>
            DB::table('zaban_meta')->where('k', 'new_per_day')->value('v'));
        $n = is_numeric($v) ? (int) $v : (int) config('zaban.new_per_day', 20);
        return max(5, min(100, $n));
    }

    public function saveNewPerDay(int $n): void
    {
        DB::table('zaban_meta')->updateOrInsert(['k' => 'new_per_day'],
            ['v' => (string) max(5, min(100, $n)), 'updated_at' => now()]);
        Cache::forget('zaban.sec.new_per_day');
    }

    /** فهرست بدون معنی؟ (پیش‌فرض: با معنی) */
    public function listMeanings(): bool
    {
        $meta = Cache::remember('zaban.sec.list_meanings', 60, fn () =>
            DB::table('zaban_meta')->where('k', 'sec_list_meanings')->value('v'));
        return $meta === null ? (bool) config('zaban.list_meanings', true) : $meta === '1';
    }

    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::FIELDS) as $n) $out[$n] = $this->get($n);
        $out['list_meanings'] = $this->listMeanings();
        return $out;
    }

    public function save(array $values, bool $listMeanings): void
    {
        foreach (self::FIELDS as $n => [, $min, $max]) {
            if (!isset($values[$n])) continue;
            $v = max($min, min($max, (int) $values[$n]));
            DB::table('zaban_meta')->updateOrInsert(['k' => "sec_$n"], ['v' => (string) $v, 'updated_at' => now()]);
            Cache::forget("zaban.sec.$n");
        }
        DB::table('zaban_meta')->updateOrInsert(['k' => 'sec_list_meanings'], ['v' => $listMeanings ? '1' : '0', 'updated_at' => now()]);
        Cache::forget('zaban.sec.list_meanings');
    }
}
