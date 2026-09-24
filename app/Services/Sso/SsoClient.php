<?php

namespace App\Services\Sso;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * لایه‌ی پروتکل OIDC — فقط با auth-server حرف می‌زند و هیچ چیزی درباره‌ی
 * جدول users نمی‌داند.
 */
class SsoClient
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('sso');

        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new RuntimeException('SSO_CLIENT_ID / SSO_CLIENT_SECRET در .env تنظیم نشده است.');
        }
    }

    public function url(string $key): string
    {
        return $this->config['issuer'] . $this->config['endpoints'][$key];
    }

    /**
     * آدرس authorize به‌علاوه‌ی مقادیری که باید در نشست بمانند.
     *
     * @return array{url:string, state:string, nonce:string, code_verifier:?string}
     */
    public function buildAuthorizationRequest(array $extra = []): array
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = null;

        $query = [
            'client_id'     => $this->config['client_id'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope'         => implode(' ', $this->config['scopes']),
            'state'         => $state,
            'nonce'         => $nonce,
        ];

        if (!empty($this->config['pkce'])) {
            $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        return [
            'url'           => $this->url('authorize') . '?' . http_build_query($query + $extra),
            'state'         => $state,
            'nonce'         => $nonce,
            'code_verifier' => $verifier,
        ];
    }

    /** تبادل code با توکن — کانال پشتی، سرور به سرور. */
    public function exchangeCodeForTokens(string $code, ?string $verifier = null): array
    {
        $payload = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri'  => $this->config['redirect_uri'],
            'code'          => $code,
        ];

        if (!empty($this->config['pkce']) && $verifier) {
            $payload['code_verifier'] = $verifier;
        }

        $response = $this->http()->asForm()->acceptJson()->post($this->url('token'), $payload);

        if ($response->failed()) {
            Log::error('[SSO] token endpoint failed', [
                'status' => $response->status(),
                'body'   => Str::limit($response->body(), 2000),
            ]);
            throw new RuntimeException('تبادل توکن با سرور احراز هویت ناموفق بود (HTTP ' . $response->status() . ').');
        }

        $tokens = $response->json();

        if (!is_array($tokens) || empty($tokens['access_token'])) {
            throw new RuntimeException('پاسخ سرور احراز هویت فاقد access_token بود.');
        }

        return $tokens;
    }

    /**
     * payload توکن id بدون بررسی امضا — فقط برای nonce و claimها. امن است
     * چون خود توکن از کانال پشتی مستقیم آمده، نه از مرورگر.
     */
    public function decodeIdTokenPayload(?string $idToken): array
    {
        if (!$idToken || substr_count($idToken, '.') !== 2) {
            return [];
        }

        [, $payload] = explode('.', $idToken, 3);
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);

        return is_array($data) ? $data : [];
    }

    /** خطای userinfo کشنده نیست؛ claimهای id_token کافی‌اند. */
    public function fetchUserInfo(string $accessToken): array
    {
        $response = $this->http()->withToken($accessToken)->acceptJson()->get($this->url('userinfo'));

        if ($response->failed()) {
            Log::warning('[SSO] userinfo endpoint failed', ['status' => $response->status()]);
            return [];
        }

        return is_array($data = $response->json()) ? $data : [];
    }

    /** @return array{uid:?string, phone:?string, name:?string} */
    public function resolveClaims(array $idToken, array $userInfo): array
    {
        $merged = !empty($this->config['trust_userinfo'])
            ? array_merge($idToken, $userInfo)
            : array_merge($userInfo, $idToken);

        if (!empty($this->config['debug_claims'])) {
            Log::info('[SSO] claims received', ['keys' => array_keys($merged)]);
        }

        return [
            'uid'   => $this->pick($merged, $this->config['claims']['uid']),
            'phone' => $this->pick($merged, $this->config['claims']['phone']),
            'name'  => $this->pick($merged, $this->config['claims']['name']),
        ];
    }

    private function pick(array $data, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($data[$k]) && $data[$k] !== '' && !is_array($data[$k])) {
                return (string) $data[$k];
            }
        }
        return null;
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->config['http']['timeout'])
            ->withOptions(['verify' => $this->config['http']['verify']]);
    }
}
