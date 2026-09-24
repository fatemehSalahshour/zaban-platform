<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * گزارش اشکال — سمت کاربر.
 *
 * شکل خروجی دقیقاً همان چیزی است که رابط در REPORTS نگه می‌دارد:
 *   { "<item_key>": { id, topic, state, seen, msgs:[{by:"me"|"admin", body, ts}] } }
 * پس رابط بدون تبدیل، جواب سرور را جای نسخه‌ی محلی می‌گذارد.
 *
 * جدا از ZabanController است چون آن کلاس از قبل یک متد report() برای
 * کارنامه‌ی آزمون دارد.
 */
class ZabanReportController extends Controller
{
    /** همان شش دکمه‌ی #repTopics در رابط. تغییرشان باید دو طرفه باشد. */
    public const TOPICS = [
        'اشتباه نگارشی', 'اشتباه علمی', 'گزینه اشتباه',
        'معنی نادرست', 'فاقد جواب تشریحی', 'سایر',
    ];

    /**
     * کلید در رابط بدون escape داخل innerHTML و data-* می‌نشیند، پس هر
     * نویسه‌ای که بتواند HTML بسازد اینجا رد می‌شود.
     */
    private const KEY_RE = '/^(w:[^<>"&\x00-\x1F]{1,80}|q:\d{4}\|[^<>"&|\x00-\x1F]{1,40}\|\d{1,3})$/u';

    public function index(Request $req): JsonResponse
    {
        return response()->json(['reports' => self::forUser($req->user()->id)]);
    }

    /** ثبت گزارش تازه. اگر کاربر قبلاً روی همین آیتم گزارش داشته، همان گفت‌وگو ادامه پیدا می‌کند. */
    public function store(Request $req): JsonResponse
    {
        $d = $req->validate([
            'key'   => ['required', 'string', 'max:191', 'regex:' . self::KEY_RE],
            't'     => ['required', 'in:w,q'],
            'id'    => ['required', 'integer', 'min:1'],
            'topic' => ['required', Rule::in(self::TOPICS)],
            'body'  => ['required', 'string', 'max:2000'],
        ]);

        if ($d['key'][0] !== $d['t']) {
            return response()->json(['error' => 'key_type_mismatch'], 422);
        }

        $exists = $d['t'] === 'w'
            ? DB::table('word_occurrences')->where('word_id', $d['id'])->exists()
            : DB::table('questions')->where('id', $d['id'])->exists();
        if (!$exists) {
            return response()->json(['error' => 'item_not_found'], 422);
        }

        $uid = $req->user()->id;
        $now = now();

        $rid = DB::transaction(function () use ($d, $uid, $now) {
            $r = DB::table('reports')
                ->where('user_id', $uid)->where('item_key', $d['key'])
                ->lockForUpdate()->first();

            if ($r) {
                DB::table('reports')->where('id', $r->id)->update([
                    'topic' => $d['topic'], 'state' => 'open', 'seen_at' => null,
                    'last_msg_at' => $now, 'updated_at' => $now,
                ]);
                $rid = $r->id;
            } else {
                $rid = DB::table('reports')->insertGetId([
                    'user_id'     => $uid,
                    'item_type'   => $d['t'] === 'w' ? 'word' : 'question',
                    'item_id'     => $d['id'],
                    'item_key'    => $d['key'],
                    'topic'       => $d['topic'],
                    'state'       => 'open',
                    'last_msg_at' => $now,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            DB::table('report_messages')->insert([
                'report_id' => $rid, 'by' => 'user', 'body' => $d['body'], 'created_at' => $now,
            ]);
            return $rid;
        });

        return response()->json(['report' => self::one($rid)]);
    }

    /** پیام تازه‌ی کاربر در گفت‌وگوی موجود. گزارش دوباره «در انتظار پاسخ» می‌شود. */
    public function reply(Request $req, int $id): JsonResponse
    {
        $d = $req->validate(['body' => ['required', 'string', 'max:2000']]);

        $r = DB::table('reports')->where('id', $id)->where('user_id', $req->user()->id)->first();
        if (!$r) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $now = now();
        DB::transaction(function () use ($id, $d, $now) {
            DB::table('report_messages')->insert([
                'report_id' => $id, 'by' => 'user', 'body' => $d['body'], 'created_at' => $now,
            ]);
            DB::table('reports')->where('id', $id)->update([
                'state' => 'open', 'last_msg_at' => $now, 'updated_at' => $now,
            ]);
        });

        return response()->json(['report' => self::one($id)]);
    }

    /** کاربر پاسخ مدیر را دید — نشان «تازه» خاموش شود. */
    public function seen(Request $req, int $id): JsonResponse
    {
        DB::table('reports')
            ->where('id', $id)->where('user_id', $req->user()->id)
            ->where('state', 'answered')->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /* ---------- کمکی‌ها ---------- */

    /**
     * همه‌ی گزارش‌های یک کاربر، کلید‌خورده با item_key.
     * object برمی‌گرداند نه array: آرایه‌ی خالی PHP در JSON «[]» می‌شود،
     * و رابط با [] به‌جای {} گزارش‌ها را در localStorage گم می‌کند.
     */
    public static function forUser(int $uid): object
    {
        $reports = DB::table('reports')->where('user_id', $uid)->get();
        if ($reports->isEmpty()) {
            return (object) [];
        }

        $msgs = DB::table('report_messages')
            ->whereIn('report_id', $reports->pluck('id'))
            ->orderBy('id')->get()->groupBy('report_id');

        $out = [];
        foreach ($reports as $r) {
            $out[$r->item_key] = self::shape($r, $msgs[$r->id] ?? collect());
        }
        return (object) $out;
    }

    private static function one(int $id): array
    {
        $r = DB::table('reports')->where('id', $id)->first();
        $msgs = DB::table('report_messages')->where('report_id', $id)->orderBy('id')->get();
        return self::shape($r, $msgs);
    }

    private static function shape(object $r, $msgs): array
    {
        return [
            'id'    => (int) $r->id,
            'topic' => $r->topic,
            'state' => $r->state,
            'seen'  => $r->seen_at !== null,
            'msgs'  => $msgs->map(fn ($m) => [
                'by'   => $m->by === 'admin' ? 'admin' : 'me',
                'body' => $m->body,
                'ts'   => Carbon::parse($m->created_at)->getTimestampMs(),
            ])->values()->all(),
        ];
    }
}
