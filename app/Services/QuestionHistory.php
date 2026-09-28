<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * تاریخچه‌ی هر تست برای هر کاربر.
 *
 * تا پیش از این، رابط فقط «چند بار زده‌ام و چند تایش درست بوده» را می‌دانست —
 * یعنی یک جمع ساده بدون ترتیب. با همان داده نمی‌شد گفت «آخرین بار غلط زده»
 * یا «دو بار آخر درست زده»، و «نزده» اصلاً جایی شمرده نمی‌شد.
 *
 * این سرویس دو منبعِ موجود را روی یک خط زمانی می‌چیند:
 *
 *   ۱) `question_attempts` — تمرین تک‌تست (بیرون از آزمون). همیشه پاسخ دارد.
 *   ۲) `exam_answers` آزمون‌های **تمام‌شده** — با `chosen = NULL` یعنی نزده.
 *      آزمون باز شمرده نمی‌شود؛ تا وقتی تصحیح نشده نتیجه‌ای ندارد.
 *
 * خروجی برای هر تست یک ردیف است با کلید `سال|رشته|شماره` (همان qKey رابط):
 *
 *   n   تعداد کل روبه‌رو شدن‌ها
 *   ok  درست، bad غلط، bl نزده
 *   opt توزیع گزینه‌های خود کاربر (۱ تا ۴) — برای نمودار «شما»
 *   h   رشته‌ی نتیجه‌ها به ترتیب زمان، قدیم → جدید: c درست، w غلط، b نزده
 *   s   منبع هر نتیجه در همان ترتیب: p تمرین، e آزمون
 *
 * وضعیت («هر بار غلط»، «جبران شده» و…) عمداً اینجا حساب نمی‌شود: رابط با هر
 * پاسخِ تازه باید بدون رفت‌وبرگشت به سرور وضعیت را به‌روز کند، پس قاعده‌اش
 * یک‌جا در جاوااسکریپت است و اینجا فقط داده‌ی خام می‌رود.
 */
class QuestionHistory
{
    /**
     * چند نتیجه‌ی آخر در `h` بماند.
     *
     * شمارنده‌ها (n/ok/bad/bl) کامل‌اند؛ این سقف فقط جلوی بزرگ شدن payload را
     * می‌گیرد. هیچ قاعده‌ای در رابط به بیش از دو نتیجه‌ی آخر نگاه نمی‌کند.
     */
    private const KEEP = 24;

    /** @return array<int, array<string, mixed>> */
    public function forUser(int $uid): array
    {
        $events = array_merge($this->practice($uid), $this->exams($uid));

        /* ترتیب زمانی — ملاک «آخرین بار» و «دو بار آخر» همین است.
           گره‌ها (یک ثانیه‌ی یکسان) با ردیفِ منبع باز می‌شوند تا مرتب‌سازی پایدار بماند. */
        usort($events, fn ($a, $b) => [$a['t'], $a['i']] <=> [$b['t'], $b['i']]);

        $out = [];
        foreach ($events as $e) {
            $k = $e['k'];
            if (!isset($out[$k])) {
                $out[$k] = ['k' => $k, 'n' => 0, 'ok' => 0, 'bad' => 0, 'bl' => 0,
                            'opt' => [0, 0, 0, 0], 'h' => '', 's' => ''];
            }
            $row = &$out[$k];
            $row['n']++;
            if ($e['r'] === 'c')      $row['ok']++;
            elseif ($e['r'] === 'w')  $row['bad']++;
            else                      $row['bl']++;

            if ($e['c'] !== null && $e['c'] >= 1 && $e['c'] <= 4) $row['opt'][$e['c'] - 1]++;

            $row['h'] .= $e['r'];
            $row['s'] .= $e['src'];
            if (strlen($row['h']) > self::KEEP) {
                $row['h'] = substr($row['h'], -self::KEEP);
                $row['s'] = substr($row['s'], -self::KEEP);
            }
            unset($row);
        }

        return array_values($out);
    }

    /**
     * تمرین‌های تک‌تست — بیرون از آزمون.
     *
     * عمداً به ستون `id` دست نمی‌زنیم: ساختار واقعی جدول‌ها اینجا تضمین‌شده
     * نیست و یک «Unknown column» کل تاریخچه را بی‌صدا خالی می‌کند. ترتیب
     * از زمان می‌آید و گره‌ها با ترتیب خود کوئری باز می‌شوند.
     */
    private function practice(int $uid): array
    {
        $rows = DB::table('question_attempts as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->where('a.user_id', $uid)
            ->orderBy('a.created_at')
            ->get(['a.chosen', 'a.is_correct', 'a.created_at',
                   'q.year', 'q.exam', 'q.question_number']);

        $i = 0;
        return $rows->map(fn ($r) => [
            'k'   => $this->key($r),
            't'   => strtotime((string) $r->created_at) ?: 0,
            'i'   => $i++,
            'r'   => $r->is_correct ? 'c' : 'w',   /* تمرین همیشه پاسخ دارد */
            'c'   => $r->chosen !== null ? (int) $r->chosen : null,
            'src' => 'p',
        ])->all();
    }

    /**
     * پاسخ‌های آزمون‌های تمام‌شده.
     *
     * زمانِ رویداد `finished_at` است نه لحظه‌ی زدنِ گزینه: پاسخ‌ها در تصحیح
     * یک‌جا نوشته می‌شوند و ترتیب داخل یک آزمون معنای مستقلی ندارد.
     */
    private function exams(int $uid): array
    {
        $rows = DB::table('exam_answers as a')
            ->join('exam_attempts as t', 't.id', '=', 'a.attempt_id')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->where('t.user_id', $uid)
            ->whereNotNull('t.finished_at')
            ->orderBy('t.finished_at')->orderBy('a.attempt_id')->orderBy('q.question_number')
            ->get(['a.chosen', 'a.is_correct', 't.finished_at',
                   'q.year', 'q.exam', 'q.question_number']);

        $i = 0;
        return $rows->map(fn ($r) => [
            'k'   => $this->key($r),
            't'   => strtotime((string) $r->finished_at) ?: 0,
            'i'   => $i++,
            'r'   => $r->chosen === null ? 'b' : ($r->is_correct ? 'c' : 'w'),
            'c'   => $r->chosen !== null ? (int) $r->chosen : null,
            'src' => 'e',
        ])->all();
    }

    /** همان کلیدی که رابط با qKey(y,e,q) می‌سازد. */
    private function key(object $r): string
    {
        return $r->year . '|' . (ContentBuilder::EXAM_FA[$r->exam] ?? $r->exam)
             . '|' . (int) $r->question_number;
    }
}
