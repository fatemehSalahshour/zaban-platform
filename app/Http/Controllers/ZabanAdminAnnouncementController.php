<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * پنل مدیریت — نوشتن و انتشار اطلاعیه.
 *
 * هر اطلاعیه یا پیش‌نویس است یا منتشرشده. انتشار دوباره‌ی یک اطلاعیه‌ی
 * برگشت‌خورده زمانش را تازه می‌کند تا برای همه دوباره «تازه» شود؛
 * ویرایش متن یک اطلاعیه‌ی منتشرشده این کار را نمی‌کند (اصلاح غلط تایپی
 * نباید زنگ همه را دوباره روشن کند).
 */
class ZabanAdminAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('zaban-admin.announcements', [
            'items'   => $this->all(),
            'editing' => null,
        ]);
    }

    public function edit(int $id): View
    {
        $a = DB::table('announcements')->where('id', $id)->first();
        abort_unless($a, 404);

        return view('zaban-admin.announcements', [
            'items'   => $this->all(),
            'editing' => $a,
        ]);
    }

    public function store(Request $req): RedirectResponse
    {
        $d = $this->validated($req);
        $publish = $req->boolean('publish');

        DB::table('announcements')->insert([
            'title'        => $d['title'],
            'body'         => $d['body'],
            'published_at' => $publish ? now() : null,
            'created_by'   => $req->user()->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->route('zadmin.announcements')
            ->with('ok', $publish ? 'اطلاعیه منتشر شد و برای همه‌ی کاربران «تازه» است.'
                                  : 'اطلاعیه به‌عنوان پیش‌نویس ذخیره شد. هنوز کسی آن را نمی‌بیند.');
    }

    public function update(Request $req, int $id): RedirectResponse
    {
        $d = $this->validated($req);
        abort_unless(DB::table('announcements')->where('id', $id)->exists(), 404);

        DB::table('announcements')->where('id', $id)->update([
            'title' => $d['title'], 'body' => $d['body'], 'updated_at' => now(),
        ]);

        return redirect()->route('zadmin.announcements')->with('ok', 'تغییرات اطلاعیه ذخیره شد.');
    }

    /** منتشر ↔ پیش‌نویس */
    public function toggle(int $id): RedirectResponse
    {
        $a = DB::table('announcements')->where('id', $id)->first();
        abort_unless($a, 404);

        $publish = $a->published_at === null;
        DB::table('announcements')->where('id', $id)->update([
            'published_at' => $publish ? now() : null, 'updated_at' => now(),
        ]);

        return back()->with('ok', $publish ? 'اطلاعیه منتشر شد.'
                                           : 'انتشار اطلاعیه برداشته شد. کاربران دیگر آن را نمی‌بینند.');
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('announcements')->where('id', $id)->delete();
        return redirect()->route('zadmin.announcements')->with('ok', 'اطلاعیه حذف شد.');
    }

    /* ---------- کمکی‌ها ---------- */

    private function all()
    {
        return DB::table('announcements')
            ->orderByRaw('published_at IS NULL DESC')   /* پیش‌نویس‌ها بالا */
            ->orderByDesc('published_at')->orderByDesc('id')
            ->get();
    }

    private function validated(Request $req): array
    {
        return $req->validate([
            'title' => ['required', 'string', 'max:200'],
            'body'  => ['required', 'string', 'max:5000'],
        ], [], ['title' => 'عنوان', 'body' => 'متن']);
    }
}
