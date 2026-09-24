<?php

/*
|--------------------------------------------------------------------------
| ورود یکپارچه — پلتفرم زبان
|--------------------------------------------------------------------------
| پیش‌فرض‌ها برای پروداکشن امن‌اند (TLS روشن، لاگ claim خاموش، SSO خاموش).
| در لوکال مقدارهای لازم را از .env بدهید.
*/

return [

    'enabled' => (bool) env('SSO_ENABLED', false),

    'issuer' => rtrim(env('SSO_ISSUER', 'https://auth.konkurcomputer.ir'), '/'),

    'endpoints' => [
        'authorize' => env('SSO_AUTHORIZE_PATH', '/oauth/authorize'),
        'token'     => env('SSO_TOKEN_PATH', '/oauth/token'),
        'userinfo'  => env('SSO_USERINFO_PATH', '/oauth/userinfo'),
    ],

    'client_id'     => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri'  => env('SSO_REDIRECT_URI'),
    'scopes'        => explode(' ', trim(env('SSO_SCOPES', 'openid profile phone'))),

    // کلاینت confidential است؛ state + nonce + secret کافی است.
    'pkce' => (bool) env('SSO_PKCE', false),

    // userinfo اولویت دارد؛ اگر در دسترس نبود، claimهای id_token کافی است.
    'trust_userinfo'       => (bool) env('SSO_TRUST_USERINFO', true),
    'allow_auto_provision' => (bool) env('SSO_AUTO_PROVISION', true),
    'debug_claims'         => (bool) env('SSO_DEBUG_CLAIMS', false),

    'http' => [
        'timeout' => (int) env('SSO_HTTP_TIMEOUT', 10),
        'verify'  => (bool) env('SSO_HTTP_VERIFY_TLS', true),
    ],

    // ترتیب مهم است: اولین کلید موجود برداشته می‌شود.
    'claims' => [
        'uid'   => ['global_uid', 'sub'],
        'phone' => ['phone_number'],
        'name'  => ['name'],
    ],

    // حساب تازه: ایمیل ساختگی روی زیردامنه‌ای که هرگز MX نمی‌گیرد.
    'fake_email_domain' => env('SSO_FAKE_EMAIL_DOMAIN', 'no-reply.konkurcomputer.ir'),

    /* خروج مرکزی */
    'logout_endpoint'       => env('SSO_LOGOUT_ENDPOINT', 'https://auth.konkurcomputer.ir/sso/logout'),
    'backchannel_secret'    => env('SSO_BACKCHANNEL_SECRET'),
    'backchannel_tolerance' => (int) env('SSO_BACKCHANNEL_TOLERANCE', 300),

    /* ورود خودکار بی‌صدا (prompt=none) — بعد از پایدار شدن روشن شود */
    'auto_login'             => (bool) env('SSO_AUTO_LOGIN', false),
    'auto_login_cooldown'    => (int) env('SSO_AUTO_LOGIN_COOLDOWN', 10),
    'central_session_cookie' => env('SSO_CENTRAL_COOKIE', 'sso'),
    'auto_login_except'      => ['sso/*', 'api/*', 'zaban-admin/*', 'payment/*', 'dev-login*'],

    'routes' => [
        'after_login'    => 'zaban',
        'after_register' => 'zaban',
        'on_failure'     => 'home',
        'admin'          => 'zadmin.dashboard',
    ],
];
