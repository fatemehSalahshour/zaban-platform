<?php

/*
| درگاه‌های پرداخت — روشن/خاموش کردن هر درگاه فقط از .env، بدون تغییر کد.
|
| کد همیشه از config('gateways…') می‌خواند، نه env()؛ روی سرور
| `php artisan optimize` تنظیمات را کش می‌کند و بعد از آن env() تهی برمی‌گرداند.
| پس بعد از هر تغییر در .env:  php artisan optimize
|
| هیچ مرچنت، رمز یا کلیدی این‌جا نوشته نمی‌شود — فقط .env.
*/

$flag = fn (string $key, bool $default) =>
    filter_var(env($key, $default), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;

return [

    'irankish' => [
        /* IRANKISH_ENABLED=false → ایران کیش در صفحه‌ی خرید نشان داده نمی‌شود
           (تنظیمات خود ایران کیش همچنان در config/irankish.php است). */
        'enabled' => $flag('IRANKISH_ENABLED', true),
    ],

    'zarinpal' => [
        /* با ZARINPAL_MERCHANT_ID روشن می‌شود؛ ZARINPAL_ENABLED=false خاموشش می‌کند */
        'enabled'     => $flag('ZARINPAL_ENABLED', true),
        'merchant_id' => trim((string) env('ZARINPAL_MERCHANT_ID', '')),

        /* true فقط برای آزمایش (sandbox.zarinpal.com) — هر مرچنت ۳۶ حرفی ساختگی پذیرفته می‌شود */
        'sandbox'     => $flag('ZARINPAL_SANDBOX', false),

        /* فایل CA به‌روز (cacert.pem موزیلا) اگر فهرست CA سیستم کهنه است.
           بررسی گواهی هیچ‌وقت خاموش نمی‌شود؛ اگر این فایل نباشد، CA خود سیستم استفاده می‌شود. */
        'ca_bundle'   => trim((string) env('ZARINPAL_CA_BUNDLE', '')),

        'timeout'         => (int) env('ZARINPAL_TIMEOUT', 25),
        'connect_timeout' => 10,
    ],
];
