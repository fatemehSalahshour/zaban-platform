<?php

/*
| درگاه پرداخت ایران کیش (IPG نسخه‌ی ۳، راهنمای فنی بازنگری ۹).
|
| همه‌ی این مقادیر را شرکت ایران کیش هنگام تعریف پایانه می‌دهد:
|   شماره پایانه (۸ رقم)، شماره پذیرنده (۱۵ رقم)، کلمه عبور (۱۶ نویسه)،
|   و کلید عمومی RSA (فایل PEM).
|
| دسترسی به سرویس دریافت نشانه با آی‌پی محدود است: آی‌پی سرور واقعی باید
| نزد ایران کیش ثبت شده باشد. از لوکال کار نمی‌کند؛ برای لوکال fake را روشن کنید.
*/
return [
    'terminal_id' => env('IRANKISH_TERMINAL_ID'),
    'acceptor_id' => env('IRANKISH_ACCEPTOR_ID'),
    'pass_phrase' => env('IRANKISH_PASS_PHRASE'),

    /* مسیر فایل PEM کلید عمومی، نسبت به ریشه‌ی پروژه (پیش‌فرض storage/irankish-public.pem) */
    'public_key_path' => env('IRANKISH_PUBLIC_KEY_PATH', 'storage/irankish-public.pem'),

    'base_url'    => env('IRANKISH_BASE_URL', 'https://ikc.shaparak.ir'),
    'timeout'     => (int) env('IRANKISH_TIMEOUT', 15),

    /*
    | درگاه آزمایشی داخلی — فقط وقتی APP_ENV=local است اثر دارد (در کد هم
    | بررسی می‌شود)، تا کل مسیر خرید بدون پایانه‌ی واقعی تست شود.
    */
    'fake' => (bool) env('IRANKISH_FAKE', false),
];
