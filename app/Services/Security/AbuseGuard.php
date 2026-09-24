<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/**
 * قفل خودکار حساب روی الگوی اسکریپت (config zaban.lock_*).
 *
 * بعد از هر دریافت تازه‌ی جزئیات کلمه یا پاسخ صدا زده می‌شود. نشانه‌ها:
 *   - سرعت: بیش از lock_words_10min کلمه‌ی متفاوت، یا lock_answers_10min پاسخ
 *     «آزاد» (بیرون از آزمون)، در ۱۰ دقیقه‌ی اخیر
 *   - تداوم: رسیدن به سقف روزانه‌ی کلمه در lock_cap_streak روز پیاپی
 * سقف روزانه سرقت را کند می‌کند؛ این قفل است که ادامه‌اش را متوقف می‌کند.
 */
class AbuseGuard
{
    public const STAFF = ['admin', 'manager', 'editor'];

    public function __construct(private Alerts $alerts, private Settings $settings) {}

    public function afterWordReveal(int $uid): void
    {
        $n = DB::table('word_reveals')->where('user_id', $uid)
            ->where('created_at', '>=', now()->subMinutes(10))->distinct()->count('word_id');
        if ($n > $this->settings->get('lock_words_10min')) {
            $this->lock($uid, "گرفتن جزئیات {$n} کلمه در ۱۰ دقیقه");
            return;
        }

        $streak = max(1, $this->settings->get('lock_cap_streak'));
        $days = DB::table('security_alerts')->where(['user_id' => $uid, 'kind' => 'word_cap'])
            ->where('day', '>', now()->subDays($streak)->toDateString())->distinct()->count('day');
        if ($days >= $streak) {
            $this->lock($uid, "رسیدن به سقف روزانه‌ی کلمه در {$days} روز پیاپی");
        }
    }

    public function afterMeaningReveal(int $uid): void
    {
        $n = DB::table('meaning_reveals')->where('user_id', $uid)
            ->where('created_at', '>=', now()->subMinutes(10))->distinct()->count('word_id');
        if ($n > $this->settings->get('lock_meanings_10min')) {
            $this->lock($uid, "دیدن معنی {$n} کلمه در ۱۰ دقیقه");
            return;
        }
        $streak = max(1, $this->settings->get('lock_cap_streak'));
        $days = DB::table('security_alerts')->where(['user_id' => $uid, 'kind' => 'meaning_cap'])
            ->where('day', '>', now()->subDays($streak)->toDateString())->distinct()->count('day');
        if ($days >= $streak) {
            $this->lock($uid, "رسیدن به سقف روزانه‌ی معنی در {$days} روز پیاپی");
        }
    }

    public function afterAnswerReveal(int $uid): void
    {
        $n = DB::table('answer_reveals')->where('user_id', $uid)->where('reason', 'free')
            ->where('created_at', '>=', now()->subMinutes(10))->distinct()->count('question_id');
        if ($n > $this->settings->get('lock_answers_10min')) {
            $this->lock($uid, "دیدن پاسخ {$n} سؤال در ۱۰ دقیقه (بیرون از آزمون)");
        }
    }

    public function lock(int $uid, string $reason): void
    {
        $user = DB::table('users')->where('id', $uid)->first(['type', 'locked_until']);
        if (!$user || in_array($user->type, self::STAFF, true)) return;     /* ادمین قفل نمی‌شود */
        if ($user->locked_until && now()->lt($user->locked_until)) return;   /* از قبل قفل است */

        $until = now()->addHours(max(1, $this->settings->get('lock_hours')));
        DB::table('users')->where('id', $uid)->update(['locked_until' => $until, 'lock_reason' => mb_substr($reason, 0, 190)]);
        $this->alerts->raise($uid, 'auto_lock', $reason . ' ← قفل تا ' . $until->format('Y-m-d H:i'));
    }

    public function unlock(int $uid): void
    {
        DB::table('users')->where('id', $uid)->update(['locked_until' => null, 'lock_reason' => null]);
    }
}
