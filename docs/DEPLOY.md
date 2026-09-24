# راه‌اندازی پلتفرم زبان روی سرور واقعی

به همین ترتیب جلو بروید. هر جا نوشته «بررسی»، تا درست نشده سراغ قدم بعد نروید.
در پایان، `php artisan zaban:preflight` باید بدون هیچ ✗ تمام شود.

همه‌ی دستورها از ریشه‌ی پروژه روی سرور (مثلاً `/var/www/zaban`) اجرا می‌شوند.
اگر زبان هم مثل auth داخل کانتینر است، دستورهای `php artisan` را با
`docker exec <کانتینر> php /مسیر/artisan …` بزنید و فایل‌ها را روی خود سرور ویرایش کنید.

---

## ۰) پیش از رفتن روی سرور (روی کامپیوتر خودتان)

```
set PATH=C:\wamp64\bin\mysql\mysql9.1.0\bin;%PATH%
php artisan test --filter=ZabanSecurity          # همه سبز
php artisan schema:dump                           # ساختار تازه برای تست‌ها
```

---

## ۱) نیازمندی‌های سرور

- PHP 8.3 با افزونه‌های `pdo_mysql`، `mbstring`، `openssl`، `curl`، `intl`
- MySQL 8 (یا MariaDB سازگار)، Composer
- دامنه زیر `konkurcomputer.ir` (کوکی نشست SSO روی دامنه‌ی مادر است) با **https**
- ریشه‌ی وب سرور (document root) = پوشه‌ی **`public`** پروژه — نه ریشه‌ی پروژه

## ۲) کد

```
git clone … /var/www/zaban      # یا آپلود؛ .env و vendor را آپلود نکنید
cd /var/www/zaban
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate          # فقط بار اول
```

## ۳) فایل `.env` سرور

```
APP_NAME="پلتفرم زبان"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://zaban.konkurcomputer.ir

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=zaban_platform
DB_USERNAME=…
DB_PASSWORD=…

# پلتفرم آزمون — با کاربر فقط‌خواندنی (قدم ۶)؛ همان نام متغیرهایی که در .env لوکال دارید

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database

# درگاه ایران کیش (قدم ۷)
IRANKISH_TERMINAL_ID=…
IRANKISH_ACCEPTOR_ID=…
IRANKISH_PASS_PHRASE=…
IRANKISH_PUBLIC_KEY_PATH=storage/irankish-public.pem
IRANKISH_FAKE=false

# منوی «پلتفرم‌ها»
PLATFORM_PLAN_URL=https://plan.konkurcomputer.ir/dashboard
PLATFORM_REVIEW_URL=https://review.konkurcomputer.ir/dashboard
PLATFORM_AZMOON_URL=https://azmoon.konkurcomputer.ir/dashboard
PLATFORM_TAKHMIN_URL=…

# SSO — طبق INSTALL.md بسته‌ی zaban-sso و docs/چکلیست_auth_انتقال_زبان.md
```

`config/app.php` → `'timezone' => 'Asia/Tehran'` (بدون این، «امروز» مرور و سقف‌های روزانه
ساعت ۳:۳۰ بامداد عوض می‌شوند).

## ۴) دیتابیس

```
php artisan migrate --force
```

روی دیتابیس خالی، لاراول اول `database/schema/mysql-schema.sql` را بارگذاری می‌کند و بعد
migrationهای تازه‌تر را. برای بارگذاری آن فایل، برنامه‌ی خط فرمان **`mysql`** باید روی سرور
باشد (`apt install mysql-client` یا `default-mysql-client`) — همان که روی ویندوز با `set PATH`
اضافه می‌کردید. **بررسی:** `php artisan migrate:status` — همه Ran.

## ۵) محتوا (بانک لغات و سؤال‌ها)

محتوا را از دیتابیس لوکال بیاورید — **فقط جدول‌های محتوا**، نه کاربران و سفارش‌های تست:

```
mysqldump -u root -p --no-create-info zaban_platform ^
  words word_examples word_occurrences exam_sections exam_texts ^
  questions question_options question_words > content.sql
```

روی سرور: `mysql -u … -p zaban_platform < content.sql`

سؤال‌های تازه بعداً از پنل ← «همگام‌سازی سؤال‌ها» می‌آیند (قدم ۶ لازم است).

## ۶) کاربر فقط‌خواندنی برای پلتفرم آزمون

```sql
CREATE USER 'zaban_ro'@'localhost' IDENTIFIED BY 'یک-رمز-قوی';
GRANT SELECT ON azmoon.* TO 'zaban_ro'@'localhost';
FLUSH PRIVILEGES;
```

همین کاربر را در `.env` برای اتصال azmoon بگذارید. دکمه‌ی همگام‌سازی حتی با خطا هم
نمی‌تواند در پلتفرم آزمون چیزی بنویسد.

## ۷) درگاه ایران کیش

1. از ایران کیش: شماره پایانه، شماره پذیرنده، کلمه عبور، فایل کلید عمومی (PEM).
2. **آی‌پی سرور را به ایران کیش اعلام کنید** — سرویس نشانه فقط از آی‌پی ثبت‌شده جواب می‌دهد.
3. کلید را در `storage/irankish-public.pem` بگذارید (نه در `public`!):
   `chmod 640 storage/irankish-public.pem` و مالک همان کاربر وب.

## ۸) دسترسی پوشه‌ها و کش

```
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**بعد از هر به‌روزرسانی کد یا .env** همین سه دستور کش دوباره.

## ۹) cron

```
* * * * * cd /var/www/zaban && php artisan schedule:run >> /dev/null 2>&1
```

بدون cron: رتبه‌بندی ساخته نمی‌شود، پرداخت‌های نیمه‌کاره تطبیق داده نمی‌شوند و آزمون‌های
رهاشده بسته نمی‌شوند. **بررسی:** preflight بخش «زمان‌بندی» را ✓ نشان دهد (چند دقیقه بعد).

## ۱۰) SSO

`docs/چکلیست_auth_انتقال_زبان.md` را قدم‌به‌قدم (سمت auth). پیش از اولین ورود مدیرها:
حساب هر مدیر باید با **شماره‌ی موبایل درست** و `type = admin` در جدول `users` باشد، وگرنه
اولین ورود SSO یک حساب تازه‌ی «دانشجو» می‌سازد.

## ۱۱) تنظیمات پنل مدیریت

`/zaban-admin` ← «قیمت و تاریخ کنکور»:
- قیمت یک، دو و سه رشته
- **تاریخ کنکور** (شمسی، مثلاً `1406/02/16`) — پایان دسترسی خریدها هم همین است
- نسخه‌ی نمایشی: سال دمو و رشته‌ها
- امنیت محتوا: سقف‌ها و قفل خودکار (پیش‌فرض‌ها معمولاً مناسب‌اند)

`/zaban-admin` ← «همگام‌سازی سؤال‌ها» ← بررسی، بعد همگام‌سازی.

## ۱۲) بررسی نهایی

```
php artisan zaban:preflight
```

همه ✓. ⚠ها پیشنهادی‌اند؛ ✗ یعنی هنوز باز نکنید.

## ۱۳) یک خرید واقعی

قیمت یک رشته را موقتاً کمینه کنید، با یک حساب واقعی بخرید و بررسی کنید:
دسترسی فعال شد، در «خرید و پرداخت‌ها» سفارش «موفق» با شماره‌ی مرجع هست، و در
`zaban_orders` وضعیت `paid`. بعد قیمت را برگردانید.

## ۱۴) بعد از باز شدن

- پنل ← «هشدارهای امنیتی» (عدد قرمز سایدبار) — روزانه نگاه کنید
- `storage/logs/laravel.log` — هفته‌ی اول روزانه
- اگر بانک جایی منتشر شد: پنل ← «ردیابی متن منتشرشده»
