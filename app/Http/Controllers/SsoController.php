<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SsoAutoLogin;
use App\Services\Sso\SsoClient;
use App\Services\Sso\SsoUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Throwable;

class SsoController extends Controller
{
    private const STATE    = 'sso.state';
    private const NONCE    = 'sso.nonce';
    private const VERIFIER = 'sso.code_verifier';
    private const SILENT   = 'sso.silent';

    private const ADMIN_TYPES = ['admin', 'manager', 'editor'];

    public function __construct(
        private SsoClient $client,
        private SsoUserResolver $resolver,
    ) {}

    /* ۱) شروع: رفتن به سرور مرکزی */
    public function redirect(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(config('sso.routes.after_login'));
        }

        $silent = $request->boolean('silent');
        $auth = $this->client->buildAuthorizationRequest($silent ? ['prompt' => 'none'] : []);

        $request->session()->put([
            self::STATE    => $auth['state'],
            self::NONCE    => $auth['nonce'],
            self::VERIFIER => $auth['code_verifier'],
            self::SILENT   => $silent,
        ]);

        /* مقصد بعد از ورود. میدل‌ور auth خودش url.intended را می‌گذارد؛
           پارامتر to فقط برای ورود بی‌صدا است و فقط آدرس داخلی پذیرفته می‌شود. */
        if ($request->filled('to') && $this->isInternal($request->string('to'))) {
            $request->session()->put('url.intended', (string) $request->string('to'));
        }

        return redirect()->away($auth['url']);
    }

    /* ۲) بازگشت از سرور مرکزی */
    public function callback(Request $request): RedirectResponse
    {
        $session = $request->session();
        $silent  = (bool) $session->pull(self::SILENT, false);

        if ($request->filled('error')) {
            $error = (string) $request->input('error');

            // در حالت بی‌صدا «وارد نیست» خطا نیست؛ کاربر نباید چیزی ببیند.
            if ($silent && in_array($error, ['login_required', 'interaction_required', 'consent_required'], true)) {
                return redirect()->to($session->pull('url.intended', route(config('sso.routes.on_failure'))));
            }

            Log::warning('[SSO] provider returned error', ['error' => $error, 'silent' => $silent]);
            return $this->fail($error === 'access_denied' ? 'ورود لغو شد.' : 'سرور احراز هویت خطا برگرداند.');
        }

        $state    = $session->pull(self::STATE);
        $nonce    = $session->pull(self::NONCE);
        $verifier = $session->pull(self::VERIFIER);

        if (!$state || !hash_equals($state, (string) $request->input('state'))) {
            Log::warning('[SSO] state mismatch');
            return $this->fail('نشست ورود منقضی شده است. لطفاً دوباره تلاش کنید.');
        }
        if (!$request->filled('code')) {
            return $this->fail('کد مجوز از سرور احراز هویت دریافت نشد.');
        }

        try {
            $tokens   = $this->client->exchangeCodeForTokens((string) $request->input('code'), $verifier);
            $idClaims = $this->client->decodeIdTokenPayload($tokens['id_token'] ?? null);

            if ($nonce && isset($idClaims['nonce']) && !hash_equals($nonce, (string) $idClaims['nonce'])) {
                Log::error('[SSO] nonce mismatch');
                return $this->fail('اعتبارسنجی توکن ناموفق بود.');
            }

            $claims = $this->client->resolveClaims($idClaims, $this->client->fetchUserInfo($tokens['access_token']));
            $result = $this->resolver->resolve($claims);
        } catch (Throwable $e) {
            Log::error('[SSO] callback failed', ['message' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
            return $this->fail($e->getMessage());
        }

        $user = $result['user'];

        /* remember=false عمدی است: ورودی که با کوکی remember برگردد نشانه‌ی
           sso.login_at ندارد و SsoSessionGuard آن را مشمول خروج مرکزی نمی‌داند.
           عمر ورود را SESSION_LIFETIME تعیین می‌کند. */
        Auth::login($user);
        $session->regenerate();
        $session->put('sso.login_at', time());
        if (!empty($tokens['id_token'])) {
            $session->put('sso.id_token', $tokens['id_token']);
        }
        Cookie::queue(Cookie::forget(SsoAutoLogin::PROBE_COOKIE));

        if ($result['created']) {
            $session->forget('url.intended');
            return redirect()->route(config('sso.routes.after_register'));
        }

        $default = in_array($user->type, self::ADMIN_TYPES, true)
            ? config('sso.routes.admin')
            : config('sso.routes.after_login');

        return redirect()->intended(route($default));
    }

    /* ۳) خروج — محلی و مرکزی */
    public function logout(Request $request): RedirectResponse
    {
        // جلوگیری از ورود خودکار فوری بعد از خروج
        Cookie::queue(Cookie::make(SsoAutoLogin::PROBE_COOKIE, '1',
            (int) config('sso.auto_login_cooldown', 10), '/', null, $request->isSecure(), true, false, 'lax'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (!config('sso.enabled')) {
            return redirect()->route('home');
        }

        return redirect()->away(config('sso.logout_endpoint')
            . '?post_logout_redirect_uri=' . urlencode(route('home')));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route(config('sso.routes.on_failure'))->with('sso_error', $message);
    }

    /** جلوگیری از open redirect: فقط مسیر نسبی یا همین دامنه. */
    private function isInternal(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return $host === null || $host === parse_url(config('app.url'), PHP_URL_HOST);
    }
}
