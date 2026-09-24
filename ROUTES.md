# تغییرات `routes/web.php`

## ۱) روت‌های تازه

```php
use App\Http\Controllers\ImpersonateController;

// ورود مدیر به حساب کاربر — کنار بقیه‌ی روت‌های zaban-admin
Route::post('/zaban-admin/login-as/{id}', [ImpersonateController::class, 'start'])
    ->whereNumber('id')
    ->middleware(['auth', 'zaban.admin'])
    ->name('impersonate.start');

// بدون zaban.admin: در لحظه‌ی برگشت، کاربرِ واردشده دانشجوست
// و EnsureAdmin او را با ۴۰۴ رد می‌کرد.
Route::get('/stop-impersonate', [ImpersonateController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonate.stop');
```

## ۲) روت `/zaban` — تزریق نوار هشدار

رابط یک فایل HTML ثابت است، پس نمی‌شود در قالب Blade نوار گذاشت. به‌جای
`response()->file(...)` این را بگذار:

```php
Route::get('/zaban', function () {
    $path = public_path('vocab-platform-v69-wired.html');

    // حالت عادی: همان فایل، بدون خواندن و دست‌کاری
    if (!session()->has(\App\Http\Controllers\ImpersonateController::KEY)) {
        return response()->file($path);
    }

    // حالت بررسی: نوار هشدار پیش از </body> تزریق می‌شود
    $html = (string) file_get_contents($path);
    $html = str_replace(
        '</body>',
        \App\Http\Controllers\ImpersonateController::banner() . '</body>',
        $html
    );

    return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
})->middleware('auth')->name('zaban');
```

نام روت صفحه‌ی کاربران مدیریت باید `zaban.admin.users` باشد (در
`ImpersonateController::stop` به آن برمی‌گردیم). اگر نام دیگری دارد، همان را
در کنترلر بگذار:

```bash
php artisan route:list --path=zaban-admin | grep -i user
```
