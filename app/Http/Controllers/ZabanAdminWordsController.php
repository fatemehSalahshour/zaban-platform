<?php

namespace App\Http\Controllers;

use App\Services\ContentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * اصلاح پیوند کلمه به سؤال.
 *
 * پیوندها از اکسل تیم محتوا می‌آیند و گاهی یک کلمه به سؤالی وصل می‌شود که در
 * آن نیست. تا حالا رفعش یعنی اصلاح اکسل و ورود دوباره‌ی کل فایل؛ اینجا مدیر
 * همان یک ردیف را می‌بیند و برمی‌دارد.
 *
 * ⚠ فقط «برداشتن» هست، نه «افزودن»: افزودن دستی یعنی داده‌ای که در اکسل نیست
 * و با اولین ورود دوباره‌ی فایل از بین می‌رود. برداشتن هم همین‌طور است، پس
 * صفحه یادآوری می‌کند که اصلاح اصلی باید در اکسل هم انجام شود.
 */
class ZabanAdminWordsController extends Controller
{
    public function index(Request $req)
    {
        $q = trim((string) $req->query('q', ''));

        $words = DB::table('words')
            ->when($q !== '', fn ($w) => $w->where('word', 'like', $q . '%'))
            ->orderBy('word')
            ->paginate(40)->withQueryString();

        /* ظهورهای همین صفحه، یک‌جا — وگرنه به ازای هر کلمه یک کوئری می‌شد */
        $ids = $words->getCollection()->pluck('id');
        $occ = $ids->isEmpty() ? collect() : DB::table('word_occurrences')
            ->whereIn('word_id', $ids)
            ->orderBy('year', 'desc')->orderBy('exam')->orderBy('test_number')
            ->get()->groupBy('word_id');

        return view('zaban-admin.words', [
            'words'  => $words,
            'occ'    => $occ,
            'q'      => $q,
            'examFa' => ContentBuilder::EXAM_FA,
            'secFa'  => ContentBuilder::SEC_FA,
        ]);
    }

    /** برداشتن یک ظهور اشتباه */
    public function destroyOccurrence(Request $req, int $id)
    {
        $row = DB::table('word_occurrences')->where('id', $id)->first();
        if (!$row) return back()->with('err', 'این ردیف پیدا نشد.');

        $word = DB::table('words')->where('id', $row->word_id)->value('word');

        DB::table('word_occurrences')->where('id', $id)->delete();

        /* شمارنده‌های کلمه از روی همین جدول ساخته شده‌اند و باید تازه شوند،
           وگرنه «در ۴ کنکور آمده» با واقعیت نمی‌خواند. */
        $this->refreshCounters((int) $row->word_id);

        return back()->with('ok', "«{$word}» از سؤال {$row->test_number} کنکور {$row->year} برداشته شد."
            . ' یادتان باشد همین اصلاح در فایل اکسل هم انجام شود، وگرنه با ورود دوباره برمی‌گردد.');
    }

    /** افزودن یا ویرایش یک ظهور. id خالی یعنی تازه. */
    public function saveOccurrence(Request $req, int $wordId)
    {
        $d = $req->validate([
            'id'             => ['nullable', 'integer'],
            'year'           => ['required', 'integer', 'min:1380', 'max:1420'],
            'exam'           => ['required', 'in:ce,it,cs'],
            'section'        => ['required', 'in:vocab,cloze,passage'],
            'test_number'    => ['nullable', 'integer', 'min:1', 'max:120'],
            'passage_number' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if (!DB::table('words')->where('id', $wordId)->exists()) {
            return back()->with('err', 'این کلمه پیدا نشد.');
        }

        /* شماره‌ی سؤال خالی یعنی کلمه در خود متن آمده، نه در سؤال */
        $inText = empty($d['test_number']);
        $pass   = $d['passage_number'] ?: null;

        /* slot همان قالبی است که ImportZaban می‌سازد؛ باید یکی بماند وگرنه
           ورود بعدی ردیف تکراری می‌سازد به‌جای به‌روز کردن همین. */
        $slot = $d['section'] === 'passage' && $pass
            ? 'passage' . $pass . ':' . ($inText ? 'text' : $d['test_number'])
            : $d['section'] . ':' . ($inText ? 'text' : $d['test_number']);

        $row = [
            'word_id'        => $wordId,
            'year'           => $d['year'],
            'exam'           => $d['exam'],
            'section'        => $d['section'],
            'test_number'    => $inText ? null : $d['test_number'],
            'passage_number' => $pass,
            'slot'           => $slot,
            'source'         => $inText ? 'text' : 'question',
            'question_id'    => null,   /* با finalize دوباره وصل می‌شود */
        ];

        if (!empty($d['id'])) {
            DB::table('word_occurrences')->where('id', $d['id'])->where('word_id', $wordId)->update($row);
            $msg = 'ردیف به‌روز شد.';
        } else {
            $dup = DB::table('word_occurrences')->where([
                'word_id' => $wordId, 'year' => $d['year'], 'exam' => $d['exam'], 'slot' => $slot,
            ])->exists();
            if ($dup) return back()->with('err', 'همین ردیف از قبل هست.');

            DB::table('word_occurrences')->insert($row);
            $msg = 'ردیف تازه اضافه شد.';
        }

        $this->refreshCounters($wordId);

        return back()->with('ok', $msg . ' برای وصل شدن به خود سؤال، یک بار'
            . ' php artisan zaban:import finalize را اجرا کنید.'
            . ' همین اصلاح را در فایل اکسل هم انجام بدهید.');
    }

    /** بازسازی شمارنده‌های یک کلمه از روی ظهورهای باقی‌مانده */
    private function refreshCounters(int $wordId): void
    {
        $rows = DB::table('word_occurrences')->where('word_id', $wordId)->get();

        $years = $rows->pluck('year')->unique();

        DB::table('words')->where('id', $wordId)->update([
            'occurrence_count' => $rows->count(),
            'year_count'       => $years->count(),
            'first_year'       => $years->min(),
            'last_year'        => $years->max(),
        ]);
    }
}
