<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * اطلاعیه‌ها — سمت کاربر.
 *
 * خروجی همان شکلی است که رابط در ANN نگه می‌دارد:
 *   { items:[{id, t, b, ts, new}], unread }
 * متن خام برمی‌گردد؛ رابط خودش escape می‌کند و خط جدید را <br> می‌کند.
 */
class ZabanAnnouncementController extends Controller
{
    private const LIMIT = 30;

    public function index(Request $req): JsonResponse
    {
        $readAt = DB::table('announcement_reads')
            ->where('user_id', $req->user()->id)->value('read_at');
        $readAt = $readAt ? Carbon::parse($readAt) : null;

        $rows = DB::table('announcements')
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderByDesc('published_at')->limit(self::LIMIT)
            ->get(['id', 'title', 'body', 'published_at']);

        $items = $rows->map(function ($r) use ($readAt) {
            $at = Carbon::parse($r->published_at);
            return [
                'id'  => (int) $r->id,
                't'   => $r->title,
                'b'   => $r->body,
                'ts'  => $at->getTimestampMs(),
                'new' => $readAt === null || $at->gt($readAt),
            ];
        })->values();

        return response()->json([
            'items'  => $items,
            'unread' => $items->where('new', true)->count(),
        ]);
    }

    /** کاربر صفحه‌ی اطلاعیه‌ها را باز کرد — همه تا این لحظه خوانده شدند. */
    public function read(Request $req): JsonResponse
    {
        DB::table('announcement_reads')->updateOrInsert(
            ['user_id' => $req->user()->id],
            ['read_at' => now()]
        );
        return response()->json(['ok' => true]);
    }
}
