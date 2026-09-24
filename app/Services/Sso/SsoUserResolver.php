<?php

namespace App\Services\Sso;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * تطبیق هویت مرکزی با کاربر این سایت.
 *
 * ترتیب جستجو:
 *   ۱) global_uid — کاربری که قبلاً از SSO آمده
 *   ۲) mobile     — کاربری که از قبل بوده (مثلاً مدیری که دستی ساخته شده)
 *                   و اولین بار از SSO می‌آید؛ به همان حساب لینک می‌شود
 *   ۳) ساخت حساب تازه
 *
 * در هر وضعیت مبهم خطا می‌دهد به‌جای حدس زدن.
 */
class SsoUserResolver
{
    public function __construct(private ?array $config = null)
    {
        $this->config ??= config('sso');
    }

    /** @return array{user:User, created:bool, linked:bool} */
    public function resolve(array $claims): array
    {
        $uid    = $claims['uid'] ?? null;
        $mobile = self::normalizeMobile($claims['phone'] ?? null);

        if (!$uid) {
            throw new RuntimeException('شناسه‌ی یکتای کاربر از سرور احراز هویت دریافت نشد.');
        }
        if (!$mobile) {
            throw new RuntimeException('شماره موبایل از سرور احراز هویت دریافت نشد.');
        }

        try {
            return DB::transaction(fn () => $this->resolveLocked($uid, $mobile, $claims['name'] ?? null));
        } catch (UniqueConstraintViolationException) {
            /* دو درخواست هم‌زمان برای یک کاربر تازه (مثلاً دو تب): یکی
               ساخت، دیگری به کلید یکتا خورد. همان حساب ساخته‌شده را برمی‌داریم. */
            $user = User::where('global_uid', $uid)->first();
            if (!$user) {
                throw new RuntimeException('ساخت حساب کاربری با تداخل روبه‌رو شد. لطفاً دوباره وارد شوید.');
            }
            return ['user' => $user, 'created' => false, 'linked' => false];
        }
    }

    private function resolveLocked(string $uid, string $mobile, ?string $name): array
    {
        // ۱) قبلاً لینک شده
        if ($user = User::where('global_uid', $uid)->lockForUpdate()->first()) {
            if ($user->mobile !== $mobile) {
                // شماره در auth عوض شده؛ auth منبع حقیقت است.
                $user->forceFill(['mobile' => $mobile])->save();
            }
            return ['user' => $user, 'created' => false, 'linked' => false];
        }

        // ۲) حساب موجود با همین موبایل، اولین ورود از SSO
        if ($user = User::where('mobile', $mobile)->lockForUpdate()->first()) {
            if ($user->global_uid && $user->global_uid !== $uid) {
                Log::error('[SSO] global_uid conflict', ['user_id' => $user->id]);
                throw new RuntimeException('این حساب قبلاً به هویت دیگری متصل شده است. با پشتیبانی تماس بگیرید.');
            }
            $user->forceFill(['global_uid' => $uid])->save();
            Log::info('[SSO] linked existing user', ['user_id' => $user->id]);
            return ['user' => $user, 'created' => false, 'linked' => true];
        }

        // ۳) کاربر تازه
        if (empty($this->config['allow_auto_provision'])) {
            throw new RuntimeException('حساب کاربری متناظر یافت نشد.');
        }

        $user = new User();
        $user->forceFill([
            'name'       => $name ?: $mobile,
            'email'      => $mobile . '@' . $this->config['fake_email_domain'],
            'mobile'     => $mobile,
            'global_uid' => $uid,
            'type'       => 'student',
            // رمز ندارد؛ هشی که با هیچ رمزی جور نمی‌شود، نه رشته‌ی خالی.
            'password'   => Hash::make(Str::random(64)),
        ])->save();

        Log::info('[SSO] provisioned new user', ['user_id' => $user->id]);

        return ['user' => $user, 'created' => true, 'linked' => true];
    }

    /** هر قالبی → 09XXXXXXXXX، یا null اگر نامعتبر بود. */
    public static function normalizeMobile(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', strtr($raw, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]));

        if (str_starts_with($digits, '0098')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10 && $digits[0] === '9') {
            $digits = '0' . $digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }
}
