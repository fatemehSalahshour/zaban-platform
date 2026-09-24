<?php

namespace App\Http\Middleware;

use App\Services\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * جلوی «رشته را در URL عوض کن، محتوای نخریده را بگیر» را می‌گیرد.
 *
 * روی هر مسیری که محتوای یک رشته را برمی‌گرداند بگذارید:
 *   Route::get('content', ...)->middleware('zaban.exam');
 *
 * پاسخ ۴۰۳ عمداً ساختاریافته است تا app-bootstrap.js بتواند
 * پی‌وال درست را با نام همان رشته نشان بدهد.
 */
class EnsureExamEntitlement
{
    public function __construct(private Entitlements $ent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $exam = strtolower((string) $request->query('exam', ''));

        if ($exam === '') {
            /* رشته نداده — به ترتیب: رشته‌ی خریداری‌شده‌ی مطابق پروفایل،
               اولین رشته‌ی خریداری‌شده، رشته‌ی پروفایل، رشته‌ی پیش‌فرض.

               دو نکته:
               - رشته‌ی ترجیحی در zaban_profiles.exam است، نه روی users؛
                 قبلاً از $user->zaban_exam می‌خواند که وجود ندارد.
               - کاربری که هیچ رشته‌ای نخریده (مثلاً تازه از SSO آمده) باید
                 به ۴۰۳ not_entitled برسد تا رابط پی‌وال نشان دهد. قبلاً
                 defaultExam برایش null می‌داد و ۴۲۲ invalid_exam برمی‌گشت،
                 که رابط فقط «بارگذاری ناموفق» نشانش می‌داد. */
            $uid       = $request->user()->id;
            $preferred = DB::table('zaban_profiles')->where('user_id', $uid)->value('exam');

            /* بعد از رشته‌های خریده، رشته‌ی دمو (اول رشته‌ی پروفایل اگر دمو است) */
            $demo = $this->ent->demoExams($uid);
            $exam = $this->ent->defaultExam($uid, $preferred)
                ?? (in_array($preferred, $demo, true) ? $preferred : ($demo[0] ?? null))
                ?? ($preferred ?: Entitlements::EXAMS[0]);
        }

        if (!in_array($exam, Entitlements::EXAMS, true)) {
            return response()->json([
                'error' => 'invalid_exam',
                'message' => 'رشته‌ی درخواستی معتبر نیست.',
            ], 422);
        }

        /* دامنه: null = کامل، سال = فقط محتوای همان سال (دمو)، false = هیچ */
        $scope = $this->ent->scope($request->user()->id, $exam);
        if ($scope === false) {
            return response()->json([
                'error'   => 'not_entitled',
                'exam'    => $exam,
                'owned'   => $this->ent->for($request->user()->id),
                'message' => 'دسترسی این رشته فعال نیست.',
                'buy_url' => '/buy?exam=' . $exam,
            ], 403);
        }

        // رشته‌ی نرمال‌شده و دامنه را به کنترلر می‌دهیم تا دوباره از query نخواند.
        $request->attributes->set('zaban_exam', $exam);
        $request->attributes->set('zaban_year', $scope);        // null یا سالِ دمو

        return $next($request);
    }
}