<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مترادف و متضاد کلمه‌ها — خروجی گرفتن و وارد کردن با یک فایل CSV.
 *
 *   php artisan zaban:relations --export
 *       → storage/app/word-relations.csv  (id, word, pos, level, meaning_fa, synonyms, antonyms)
 *         با اکسل باز می‌شود (UTF-8 با BOM). ستون‌های synonyms و antonyms را پر یا اصلاح کنید.
 *
 *   php artisan zaban:relations --import=storage/app/word-relations.csv --check   فقط گزارش
 *   php artisan zaban:relations --import=storage/app/word-relations.csv           ذخیره
 *
 * در هر خانه کلمه‌ها با ویرگول جدا می‌شوند («reduce, lessen, ease»). خانه‌ی خالی یعنی
 * «دست نزن»؛ برای پاک کردن یک خط تیره بگذارید (-). خود کلمه، تکراری‌ها و هر چیزی که
 * حرف انگلیسی نیست کنار گذاشته می‌شود؛ حداکثر ۶ مترادف و ۴ متضاد.
 */
class WordRelations extends Command
{
    protected $signature = 'zaban:relations
        {--export   : خروجی CSV همه‌ی کلمه‌ها با مترادف/متضاد فعلی}
        {--import=  : مسیر فایل CSV برای وارد کردن}
        {--check    : همراه --import: فقط گزارش، چیزی ذخیره نمی‌شود}';

    protected $description = 'خروجی و ورود مترادف و متضاد کلمه‌ها (CSV)';

    private const MAX_SYN = 6;
    private const MAX_ANT = 4;

    public function handle(): int
    {
        if (!Schema::hasColumn('words', 'synonyms')) {
            $this->error('ستون synonyms در جدول words نیست — اول migration را اجرا کنید:');
            $this->line('  php artisan migrate --path=database/migrations/2026_10_08_100000_words_relations.php --force');
            return 1;
        }
        if ($this->option('export')) return $this->export();
        if ($p = $this->option('import')) return $this->import($p, (bool) $this->option('check'));

        $this->line('یکی از --export یا --import=مسیر را بدهید.');
        return 1;
    }

    private function export(): int
    {
        $path = storage_path('app/word-relations.csv');
        $f = fopen($path, 'w');
        fwrite($f, "\xEF\xBB\xBF");                       /* BOM — اکسل فارسی را درست باز کند */
        fputcsv($f, ['id', 'word', 'pos', 'level', 'meaning_fa', 'synonyms', 'antonyms']);
        $n = 0;
        DB::table('words')->orderBy('id')
            ->select('id', 'word', 'pos_primary', 'level', 'meaning_fa', 'synonyms', 'antonyms')
            ->chunkById(1000, function ($rows) use ($f, &$n) {
                foreach ($rows as $w) {
                    fputcsv($f, [$w->id, $w->word, $w->pos_primary, $w->level,
                                 mb_substr((string) $w->meaning_fa, 0, 160), $w->synonyms, $w->antonyms]);
                    $n++;
                }
            });
        fclose($f);
        $this->info("{$n} کلمه نوشته شد: {$path}");
        return 0;
    }

    private function import(string $path, bool $check): int
    {
        $path = is_file($path) ? $path : base_path($path);
        if (!is_file($path)) { $this->error("فایل پیدا نشد: {$path}"); return 1; }

        $f = fopen($path, 'r');
        $head = fgetcsv($f);
        if (!$head) { $this->error('فایل خالی است.'); return 1; }
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
        $col = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $head));
        foreach (['word', 'synonyms', 'antonyms'] as $need) {
            if (!isset($col[$need])) { $this->error("ستون «{$need}» در فایل نیست."); return 1; }
        }

        $byId = DB::table('words')->pluck('word', 'id')->all();
        $byWord = [];
        foreach ($byId as $id => $w) $byWord[mb_strtolower($w)] ??= $id;

        $upd = $same = $miss = $empty = 0; $samples = [];
        while (($r = fgetcsv($f)) !== false) {
            $word = trim((string) ($r[$col['word']] ?? ''));
            $id = isset($col['id']) ? (int) ($r[$col['id']] ?? 0) : 0;
            if (!$id || !isset($byId[$id])) $id = $byWord[mb_strtolower($word)] ?? 0;
            if (!$id) { if ($word !== '') $miss++; continue; }

            $data = [];
            foreach (['synonyms' => self::MAX_SYN, 'antonyms' => self::MAX_ANT] as $c => $max) {
                $raw = trim((string) ($r[$col[$c]] ?? ''));
                if ($raw === '') continue;                                /* خالی = دست نزن */
                $data[$c] = $raw === '-' ? null : (self::clean($raw, $byId[$id], $max) ?: null);
            }
            if (!$data) { $empty++; continue; }

            $cur = DB::table('words')->where('id', $id)->first(['synonyms', 'antonyms']);
            if (collect($data)->every(fn ($v, $k) => $cur->$k === $v)) { $same++; continue; }

            $upd++;
            if (count($samples) < 12) $samples[] = [$byId[$id], $data['synonyms'] ?? '…', $data['antonyms'] ?? '…'];
            if (!$check) DB::table('words')->where('id', $id)->update($data);
        }
        fclose($f);

        if ($samples) $this->table(['کلمه', 'مترادف', 'متضاد'], $samples);
        $this->info("به‌روز: {$upd}   بدون تغییر: {$same}   خانه‌ی خالی: {$empty}   کلمه‌ی ناشناخته: {$miss}");
        if ($check) $this->info('حالت بررسی — چیزی ذخیره نشد. برای ذخیره همین دستور را بدون --check بزنید.');
        return 0;
    }

    /** «Reduce; lessen ، ease» → «reduce, lessen, ease» */
    private static function clean(string $raw, string $self, int $max): string
    {
        $out = [];
        foreach (preg_split('/[,;،|\n]+/u', $raw) as $x) {
            $x = trim(preg_replace('/\s+/u', ' ', $x));
            if ($x === '' || !preg_match("/^[A-Za-z][A-Za-z '\\-]{0,40}$/", $x)) continue;
            if (strcasecmp($x, $self) === 0) continue;
            $out[mb_strtolower($x)] ??= $x;
        }
        return implode(', ', array_slice(array_values($out), 0, $max));
    }
}
