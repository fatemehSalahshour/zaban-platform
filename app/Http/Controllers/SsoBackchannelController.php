<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * درِ پشتی که فقط auth-server صدا می‌زند. مسیر عمومی است؛ تنها محافظش امضای
 * HMAC است — بدون بررسی درست امضا، هر کسی می‌توانست هر کاربری را بیرون بیندازد.
 */
class SsoBackchannelController extends Controller
{
    public function logout(Request $request): JsonResponse
    {
        $secret = config('sso.backchannel_secret');

        if (!$secret) {
            Log::error('[SSO] backchannel secret not configured');
            return response()->json(['error' => 'not_configured'], 500);
        }

        $sub       = (string) $request->input('sub');
        $timestamp = (string) $request->header('X-SSO-Timestamp');
        $signature = (string) $request->header('X-SSO-Signature');

        if ($sub === '' || $timestamp === '' || $signature === '') {
            return response()->json(['error' => 'bad_request'], 400);
        }

        // پنجره‌ی زمانی: جلوگیری از تکرار درخواست ضبط‌شده.
        if (abs(time() - (int) $timestamp) > (int) config('sso.backchannel_tolerance', 300)) {
            return response()->json(['error' => 'stale'], 401);
        }

        if (!hash_equals(hash_hmac('sha256', $sub . '|' . $timestamp, $secret), $signature)) {
            Log::warning('[SSO] backchannel signature mismatch');
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $affected = User::where('global_uid', $sub)->update(['sso_logout_at' => now()]);

        Log::info('[SSO] backchannel logout accepted', ['affected' => $affected]);

        // حتی اگر کاربری با این شناسه نبود پاسخ موفق — auth نباید بفهمد چه کسی کجا حساب دارد.
        return response()->json(['ok' => true]);
    }
}
