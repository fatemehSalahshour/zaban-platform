<?php

namespace App\Support;

/**
 * نقش‌ها و اختیار هر نقش در پنل مدیریت — یک جا، برای «دیدن» و «ذخیره» هر دو.
 * (کیت امنیت، بخش ۲-۱۷ و ۲-۱۸)
 *
 * تا پیش از این، هر سه نقشِ کارکنان دسترسی کامل داشتند: ویراستار می‌توانست قیمت
 * و درگاه را عوض کند، سفارش را دستی فعال کند، نقش خودش را «مدیر کل» کند و وارد
 * حساب مدیر کل شود.
 *
 * هر بخش پنل یک «حوزه» است؛ از روی نام route تعیین می‌شود (EnsureAdmin). route
 * ناشناخته فقط برای مدیر کل باز است — بستن پیش‌فرض، نه باز گذاشتن.
 */
final class Roles
{
    public const STAFF = ['admin', 'manager', 'editor'];

    public const LABELS = [
        'student' => 'دانشجو',
        'editor'  => 'ویراستار محتوا',
        'manager' => 'مدیر محتوا',
        'admin'   => 'مدیر کل',
    ];

    /** حوزه‌های هر نقش. مدیر کل همه‌جا. */
    private const AREAS = [
        'admin'   => ['*'],
        'manager' => ['dashboard', 'content', 'words', 'sync', 'import', 'reports', 'announcements', 'alerts', 'leak'],
        'editor'  => ['dashboard', 'words', 'import', 'reports'],
    ];

    /** توضیح ساده برای صفحه‌ی ویرایش کاربر */
    public const HELP = [
        'student' => 'فقط پلتفرم؛ بدون پنل مدیریت.',
        'editor'  => 'اصلاح کلمه‌ها و ظهورها، و پاسخ به گزارش‌های کاربران.',
        'manager' => 'همه‌ی کارهای محتوا: کلمه‌ها، همگام‌سازی سؤال‌ها، اطلاعیه‌ها، گزارش‌ها، هشدارهای امنیتی و ردیابی متن.',
        'admin'   => 'همه‌چیز، از جمله قیمت و درگاه، سفارش‌ها و فعال‌سازی دستی، کاربران و نقش‌ها، و ورود به حساب دانشجو.',
    ];

    /** بخش اول نام route (بعد از «zadmin.») → حوزه */
    private const SEGMENT_AREAS = [
        'dashboard' => 'dashboard', 'settings' => 'settings',
        'users' => 'users', 'user' => 'users',
        'orders' => 'orders', 'order' => 'orders',
        'words' => 'words', 'content' => 'content', 'reports' => 'reports',
        'alerts' => 'alerts', 'secreport' => 'alerts', 'leak' => 'leak', 'sync' => 'sync',
        'import' => 'import', 'announcements' => 'announcements',
    ];

    public static function isStaff(?object $user): bool
    {
        return $user !== null && in_array($user->type ?? 'student', self::STAFF, true);
    }

    public static function isAdmin(?object $user): bool
    {
        return $user !== null && ($user->type ?? null) === 'admin';
    }

    public static function can(?object $user, string $area): bool
    {
        if (!self::isStaff($user)) return false;
        $areas = self::AREAS[$user->type] ?? [];
        return in_array('*', $areas, true) || in_array($area, $areas, true);
    }

    /** حوزه‌ی یک route؛ null یعنی ناشناخته (فقط مدیر کل) */
    public static function areaOfRoute(?string $name): ?string
    {
        if ($name === 'impersonate.start') return 'impersonate';
        if ($name === 'zadmin.users.unlock') return 'alerts';     /* باز کردن قفل خودکار — کار امنیتی */
        if (!$name || !str_starts_with($name, 'zadmin.')) return null;
        $seg = explode('.', substr($name, 7))[0];
        return self::SEGMENT_AREAS[$seg] ?? null;
    }
}
