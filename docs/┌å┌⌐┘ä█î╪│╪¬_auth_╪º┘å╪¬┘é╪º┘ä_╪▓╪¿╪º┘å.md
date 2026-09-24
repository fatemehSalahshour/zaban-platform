# چک‌لیست auth پروداکشن — روز انتقال پلتفرم زبان

این فایل فقط تغییرات **سرور auth** را پوشش می‌دهد. تنظیمات خود زبان در
`INSTALL.md` بسته‌ی `zaban-sso` است.

**قاعده‌ی دستورهای auth:** همیشه از داخل کانتینر:

```bash
docker exec auth-joomla-php83-1 php /var/www/konkurix/artisan <دستور>
```

**قاعده‌ی ویرایش فایل‌ها:** روی خود سرور در `/var/www/auth/...`، نه داخل کانتینر.
کانتینر اجازه‌ی نوشتن ندارد (`sed: Permission denied`). مسیر `/var/www/auth` روی
سرور همان `/var/www/konkurix` داخل کانتینر است.

---

## ترتیب کار

| مرحله | کی | اثر روی کاربران |
| --- | --- | --- |
| ۱. فایل `sso_clients.php` | هر وقت، قبل از انتقال | هیچ — تا `.env` تنظیم نشود خاموش است |
| ۲. ساخت کلاینت OAuth | هر وقت، قبل از انتقال | هیچ |
| ۳. ساخت secret | همراه مرحله‌ی ۲ | هیچ |
| ۴. `.env` و روشن کردن | **فقط روز انتقال**، بعد از بالا آمدن زبان | خروج مرکزی شامل زبان می‌شود |

---

## ۱) فایل `config/sso_clients.php`

### قبل از هر چیز: بلوک `plan` را کامل ببین

```bash
grep -n "'plan'" -A10 /var/www/auth/config/sso_clients.php
```

بلوک `zaban` باید **همه‌ی کلیدهای** بلوک `plan` را داشته باشد. در لوکال فقط سه کلید
`enabled`، `logout_url` و `secret` دیده شد؛ اگر کلید دیگری هست، به بلوک زیر اضافه کن.

### بلوک زبان

درست بعد از بسته شدن بلوک `plan`:

```php
        'zaban' => [
            'enabled'    => env('SSO_SITE_ZABAN_ENABLED', false),
            'logout_url' => env('SSO_SITE_ZABAN_LOGOUT_URL'),
            'secret'     => env('SSO_SITE_ZABAN_SECRET'),
        ],
```

پیش‌فرض `enabled` عمداً `false` است و آدرس پیش‌فرض ندارد: اگر `.env` تنظیم نشده باشد،
auth بی‌صدا به آدرس لوکال درخواست نمی‌فرستد. (همین اشتباه یک بار با مرور و یک بار با
Plan پیش آمد و خروج مرکزی آن‌ها را از کار انداخت.)

### اعمال

بهترین راه از گیت:

```bash
cd /var/www/auth
git status --short          # باید تمیز باشد
git pull origin main
grep -n "zaban" -A4 config/sso_clients.php
```

یا با WinSCP در `/var/www/auth/config/`.

### بررسی

```bash
docker exec auth-joomla-php83-1 php -l /var/www/konkurix/config/sso_clients.php
docker exec auth-joomla-php83-1 php /var/www/konkurix/artisan config:clear
```

**بلافاصله یک ورود در مرور تست کن.** خطای نحوی در این فایل ورود همه‌ی سایت‌ها را
می‌خواباند.

---

## ۲) کلاینت OAuth

```bash
docker exec auth-joomla-php83-1 php /var/www/konkurix/artisan passport:client \
  --redirect_uri="https://zaban.konkurcomputer.ir/sso/callback" --name="zaban"
```

`Client ID` و `Client secret` را **همان لحظه** کپی کن. secret فقط یک بار نمایش داده
می‌شود و در دیتابیس هش می‌شود؛ اگر گم شود باید کلاینت را دوباره ساخت.

- دامنه‌ی زبان باید زیر `konkurcomputer.ir` باشد. کوکی نشست مرکزی روی همین دامنه‌ی
  مادر است و ورود خودکار فقط زیر آن کار می‌کند.
- اگر دامنه‌ی نهایی چیز دیگری است، در همه‌ی این فایل جایگزینش کن.

بررسی:

```sql
SELECT id, name, redirect_uris, revoked FROM auth.oauth_clients WHERE name = 'zaban';
```

---

## ۳) secret برای backchannel

```bash
openssl rand -hex 32
```

همین یک مقدار در دو جا می‌نشیند:

| سایت | متغیر |
| --- | --- |
| auth | `SSO_SITE_ZABAN_SECRET` |
| زبان | `SSO_BACKCHANNEL_SECRET` |

---

## ۴) `.env` auth — فقط روز انتقال

**پیش‌شرط:** زبان روی سرور بالا باشد و این آدرس جواب بدهد:

```bash
docker exec auth-joomla-php83-1 sh -c \
  "curl -s -o /dev/null -w '%{http_code}\n' -X POST https://zaban.konkurcomputer.ir/sso/backchannel-logout"
```

| کد | یعنی |
| --- | --- |
| `400` | سالم — مسیر وجود دارد، فقط پارامتر نداشت |
| `419` | استثنای CSRF در `bootstrap/app.php` زبان نیست |
| `404` | روت‌های SSO زبان ثبت نشده |
| `000` | کانتینر به زبان دسترسی ندارد (DNS یا شبکه) |

### ویرایش

در `/var/www/auth/.env` روی سرور:

```
SSO_SITE_ZABAN_ENABLED=true
SSO_SITE_ZABAN_LOGOUT_URL=https://zaban.konkurcomputer.ir/sso/backchannel-logout
SSO_SITE_ZABAN_SECRET=<خروجی مرحله‌ی ۳>
```

### تأیید و اعمال

```bash
# کانتینر همان فایلی را می‌بیند که ویرایش شد؟
docker exec auth-joomla-php83-1 sh -c "grep -E 'SSO_SITE_ZABAN' /var/www/konkurix/.env"

docker exec auth-joomla-php83-1 php /var/www/konkurix/artisan config:clear
```

`ENABLED=true` را زودتر نگذار: هر خروج از هر سایتی یک درخواست ناموفق به زبان می‌فرستد
و لاگ پر از `zaban:false` می‌شود. کاربران چیزی نمی‌بینند، ولی تشخیص مشکل‌های واقعی
سخت می‌شود.

---

## تست نهایی

1. در مرور و زبان با SSO وارد شو.
2. از مرور خارج شو.
3. لاگ auth:

   ```bash
   docker exec auth-joomla-php83-1 sh -c \
     "grep -a 'notification round' /var/www/konkurix/storage/logs/laravel.log | tail -1"
   ```

   باید باشد:

   ```
   {"review":true,"azmoon":true,"plan":true,"zaban":true}
   ```

4. در زبان یک صفحه رفرش کن — باید خارج شده باشی.

### اگر `zaban:false` بود

دلیل دقیق در خط قبل از `notification round`:

```bash
docker exec auth-joomla-php83-1 sh -c \
  "grep -a 'SSO-LOGOUT' /var/www/konkurix/storage/logs/laravel.log | tail -3 | cut -c1-400"
```

مقایسه‌ی secret دو طرف (مقدار چاپ نمی‌شود، فقط هش):

```bash
docker exec auth-joomla-php83-1 sh -c \
  "grep '^SSO_SITE_ZABAN_SECRET=' /var/www/konkurix/.env | cut -d= -f2- | tr -d '\r' | sha256sum"
grep '^SSO_BACKCHANNEL_SECRET=' <مسیر زبان>/.env | cut -d= -f2- | tr -d '\r' | sha256sum
```

دو هش باید یکسان باشند.

---

## کار عقب‌مانده روی همین auth: مجوز کلید

`oauth-public.key` هنوز مجوز ۶۴۴ دارد و هر درخواست userinfo خطای ۵۰۰ می‌گیرد:

```
Key file "oauth-public.key" permissions are not correct ... 644
```

ورود کار می‌کند چون `id_token` کافی است، ولی لاگ پر می‌شود. روز انتقال فرصت خوبی است.
**از روی خود سرور**، نه داخل کانتینر (داخل کانتینر `Operation not permitted` می‌دهد):

```bash
ls -ln /var/www/auth/storage/oauth-*.key      # مالک هر دو باید یکی باشد (معمولاً 33)
chmod 600 /var/www/auth/storage/oauth-public.key
docker exec auth-joomla-php83-1 sh -c "ls -l /var/www/konkurix/storage/oauth-*.key"
```

خروجی آخر باید برای هر دو `-rw-------` با مالک `www-data` باشد.

**بلافاصله یک ورود در مرور تست کن.** اگر خراب شد، فوراً:

```bash
chmod 644 /var/www/auth/storage/oauth-public.key
```

بعد از اصلاح، در لاگ سایت‌ها دیگر نباید `userinfo endpoint failed` دیده شود.
