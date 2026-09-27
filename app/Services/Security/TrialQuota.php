<?php

namespace App\Services\Security;

use App\Services\Entitlements;
use Illuminate\Support\Facades\DB;

/**
 * کیف نسخه‌ی آزمایشی.
 *
 * کاربری که هنوز نخریده همه‌ی سال‌ها و رشته‌ها را می‌بیند، ولی فقط تا سقف
 * مشخصی کلمه‌ی متمایز می‌تواند «باز کند» (معنی و مثال و جزئیات).
 *
 * سه نکته‌ی طراحی:
 *
 * ۱. سقف بر «کلمه‌ی متمایز» است نه بر کلیک. کلمه‌ای که یک بار باز شده تا ابد
 *    رایگان است. وگرنه کاربر از کلیک کردن می‌ترسد و همان تجربه‌ای که می‌خواستیم
 *    نشانش بدهیم خراب می‌شود.
 *
 * ۲. کیف مشترک است: دیدن معنی و دیدن جزئیاتِ همان کلمه یک واحد حساب می‌شود،
 *    نه دو تا. کاربر این دو را یک کار می‌بیند.
 *
 * ۳. مجموعی است نه روزانه. سقف روزانه‌ی خریداران (WordQuota و MeaningQuota)
 *    کار دیگری می‌کند: جلوی کپی‌برداری انبوه را می‌گیرد. آن‌ها سر جایشان
 *    می‌مانند و این یکی رویشان سوار می‌شود.
 */
class TrialQuota
{
    public function __construct(private Entitlements $ent, private Alerts $alerts) {}

    /** این کاربر اصلاً در حالت آزمایشی است؟ (هیچ رشته‌ای نخریده) */
    public function applies(int $userId): bool
    {
        return $this->ent->trialOn() && !$this->ent->for($userId);
    }

    public function cap(): int
    {
        return $this->ent->trialCap();
    }

    /** کلمه‌هایی که تا حالا باز کرده — از هر دو جدول، یکتا */
    public function usedIds(int $userId): array
    {
        $a = DB::table('word_reveals')->where('user_id', $userId)
            ->distinct()->pluck('word_id')->all();
        $b = DB::table('meaning_reveals')->where('user_id', $userId)
            ->distinct()->pluck('word_id')->all();

        return array_values(array_unique(array_map('intval', array_merge($a, $b))));
    }

    public function used(int $userId): int
    {
        return count($this->usedIds($userId));
    }

    public function remaining(int $userId): int
    {
        return max(0, $this->cap() - $this->used($userId));
    }

    /**
     * از این کلمه‌ها کدام‌ها در سهمیه جا می‌شوند.
     *
     * @return array{allowed:int[], limited:int[]}
     */
    public function filter(int $userId, array $wordIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $wordIds)));
        if (!$ids || !$this->applies($userId)) {
            return ['allowed' => $ids, 'limited' => []];
        }

        $seen = array_flip($this->usedIds($userId));
        $room = max(0, $this->cap() - count($seen));

        $allowed = []; $limited = [];
        foreach ($ids as $w) {
            if (isset($seen[$w])) { $allowed[] = $w; }          /* قبلاً باز شده — رایگان */
            elseif ($room > 0)    { $allowed[] = $w; $room--; }
            else                  { $limited[] = $w; }
        }

        if ($limited) {
            $this->alerts->raise($userId, 'trial_cap',
                'سهمیه‌ی نسخه‌ی آزمایشی (' . $this->cap() . ' کلمه) پر شد.');
        }

        return ['allowed' => $allowed, 'limited' => $limited];
    }

    public function message(): string
    {
        return 'سهمیه‌ی نسخه‌ی آزمایشی شما (' . $this->cap()
             . ' کلمه) پر شده است. برای دسترسی به همه‌ی بانک، رشته‌تان را تهیه کنید.';
    }
}
