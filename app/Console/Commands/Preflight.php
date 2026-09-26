<?php

namespace App\Console\Commands;

use App\Services\Pricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * php artisan zaban:preflight — آیا سرور برای باز شدن به روی کاربران آماده است؟
 * هر مورد ✓ (درست) / ⚠ (بهتر است درست شود) / ✗ (پیش از باز کردن حتماً). کد خروج ۱ اگر ✗ باشد.
 * روی سرور بعد از هر نصب اجرا شود؛ جزئیات هر مورد در docs/DEPLOY.md.
 */
class Preflight extends Command
{
    protected $signature = 'zaban:preflight';
    protected $description = 'بررسی آمادگی سرور واقعی پیش از باز شدن به روی کاربران';

    private int $fail = 0;
    private int $warn = 0;

    public function handle(): int
    {
        $this->line('');
        $this->section('برنامه');
        $this->check(app()->environment('production'), 'APP_ENV=production', 'APP_ENV باید production باشد (الان: ' . app()->environment() . ')');
        $this->check(!config('app.debug'), 'APP_DEBUG خاموش', 'APP_DEBUG=true روی سرور متن خطا و تنظیمات را به کاربر نشان می‌دهد');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL با https', 'APP_URL باید https و دامنه‌ی واقعی باشد (برگشت درگاه از روی آن ساخته می‌شود)');
        $this->check(config('app.timezone') === 'Asia/Tehran', 'منطقه‌ی زمانی Asia/Tehran', "config/app.php → 'timezone' => 'Asia/Tehran' (الان: " . config('app.timezone') . ')');
        $this->check(!collect(Route::getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), 'dev-login')), 'مسیر /dev-login بسته است', 'مسیر /dev-login باز است — هر کسی بدون رمز وارد هر حسابی می‌شود');
        $this->soft((bool) config('session.secure'), 'کوکی نشست فقط روی https', 'SESSION_SECURE_COOKIE=true بگذارید');
        $this->soft(app()->configurationIsCached(), 'تنظیمات کش شده (config:cache)', 'برای سرعت: php artisan config:cache && php artisan route:cache && php artisan view:cache');
        $this->check(is_writable(storage_path('logs')) && is_writable(storage_path('framework')), 'پوشه‌ی storage قابل نوشتن', 'storage/logs و storage/framework باید برای کاربر وب قابل نوشتن باشند');

        $this->section('ورود یکپارچه (SSO)');
        $this->check(config('sso') !== null && (bool) config('sso.enabled'), 'SSO روشن', 'SSO خاموش یا تنظیم نشده — docs/چکلیست_auth_انتقال_زبان.md');
        $admins = DB::table('users')->whereIn('type', ['admin', 'manager'])->whereNotNull('mobile')->count();
        $this->check($admins > 0, "حساب مدیر با موبایل ({$admins})", 'هیچ مدیری با شماره‌ی موبایل نیست؛ اولین ورود SSO حساب تازه‌ی «دانشجو» می‌سازد و پنل در دسترس نیست');

        $this->section('درگاه ایران کیش');
        $ik = config('irankish');
        $this->check(!($ik['fake'] ?? false), 'درگاه آزمایشی خاموش', 'IRANKISH_FAKE=false بگذارید');
        $this->check(!empty($ik['terminal_id']) && !empty($ik['acceptor_id']) && !empty($ik['pass_phrase']),
            'شماره پایانه، پذیرنده و کلمه عبور', 'IRANKISH_TERMINAL_ID / ACCEPTOR_ID / PASS_PHRASE در .env');
        $keyPath = base_path((string) ($ik['public_key_path'] ?? ''));
        $keyOk = is_file($keyPath) && @openssl_pkey_get_public((string) file_get_contents($keyPath)) !== false;
        $this->check($keyOk, 'کلید عمومی ایران کیش معتبر', "فایل کلید پیدا نشد یا PEM معتبر نیست: {$keyPath}");
        $this->soft(!str_starts_with($keyPath, public_path()), 'کلید بیرون از public', 'کلید درگاه نباید در پوشه‌ی public باشد');

        $this->section('زمان‌بندی (cron)');
        $hb = DB::table('zaban_meta')->where('k', 'schedule_heartbeat')->value('v');
        $this->check($hb && now()->diffInMinutes($hb, true) <= 3, 'cron اجرا می‌شود' . ($hb ? " (آخرین: {$hb})" : ''),
            'cron اجرا نمی‌شود — بدون آن رتبه‌بندی، تطبیق پرداخت‌ها و بستن آزمون‌های رهاشده کار نمی‌کند: * * * * * php artisan schedule:run');

        $this->section('پلتفرم آزمون (azmoon)');
        try {
            DB::connection('azmoon')->select('SELECT 1');
            $this->check(true, 'اتصال به دیتابیس پلتفرم آزمون', '');
            $grants = collect(DB::connection('azmoon')->select('SHOW GRANTS FOR CURRENT_USER()'))
                ->map(fn ($r) => (string) array_values((array) $r)[0])->implode(' ');
            $write = preg_match('/\b(ALL PRIVILEGES|INSERT|UPDATE|DELETE|DROP|ALTER)\b/i', $grants);
            $this->soft(!$write, 'کاربر azmoon فقط‌خواندنی', 'کاربر اتصال azmoon اجازه‌ی نوشتن دارد؛ یک کاربر فقط SELECT بسازید (docs/DEPLOY.md)');

            /* رشته‌ی هر سؤال از جدول واسط question_major خوانده می‌شود. بدون آن،
               همگام‌سازی خطای SQL می‌دهد؛ این بررسی زودتر و روشن‌تر می‌گویدش. */
            $hasPivot = DB::connection('azmoon')->getSchemaBuilder()->hasTable('question_major');
            $this->check($hasPivot, 'جدول question_major در azmoon',
                'جدول question_major نیست — همگام‌سازی سؤال‌ها کار نمی‌کند. سمت پلتفرم آزمون باید اعمال شود.');
        } catch (\Throwable $e) {
            $this->check(false, '', 'اتصال به azmoon برقرار نشد: ' . mb_substr($e->getMessage(), 0, 120));
        }

        $this->section('محتوا و تنظیمات');
        $words = DB::table('words')->count();
        $qs = DB::table('questions')->count();
        $this->check($words > 0, "بانک لغات ({$words} کلمه)", 'بانک لغات خالی است — zaban:import');
        $this->check($qs > 0, "سؤال‌ها ({$qs})", 'سؤالی نیست — پنل ← همگام‌سازی سؤال‌ها');
        $until = app(Pricing::class)->accessUntil();
        $this->check($until && now()->lt($until), 'تاریخ کنکور در آینده' . ($until ? " ({$until})" : ''),
            'تاریخ کنکور تنظیم نشده یا گذشته — پنل ← قیمت و تاریخ کنکور (پایان دسترسی خریدهاست)');
        $missing = collect(config('platforms.list', []))->filter(fn ($p) => empty($p['url']))->pluck('n')->all();
        $this->soft(!$missing, 'آدرس همه‌ی پلتفرم‌ها', 'بی‌آدرس در منوی «پلتفرم‌ها»: ' . implode('، ', $missing));

        $this->line('');
        if ($this->fail) {
            $this->error("  {$this->fail} مورد باید پیش از باز کردن درست شود" . ($this->warn ? " و {$this->warn} مورد بهتر است." : '.'));
            return self::FAILURE;
        }
        $this->info('  آماده است' . ($this->warn ? " — {$this->warn} مورد پیشنهادی باقی است." : '.'));
        return self::SUCCESS;
    }

    private function section(string $t): void { $this->line(''); $this->line("<options=bold>{$t}</>"); }

    private function check(bool $ok, string $good, string $bad): void
    {
        if ($ok) { $this->line("  <fg=green>✓</> {$good}"); return; }
        $this->fail++; $this->line("  <fg=red>✗</> {$bad}");
    }

    private function soft(bool $ok, string $good, string $bad): void
    {
        if ($ok) { $this->line("  <fg=green>✓</> {$good}"); return; }
        $this->warn++; $this->line("  <fg=yellow>⚠</> {$bad}");
    }
}
