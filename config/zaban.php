<?php

/*
| امنیت محتوای پلتفرم زبان (تصمیم کاربر، ۳۱ شهریور ۱۴۰۵).
*/
return [
    /* حداکثر سؤال متفاوتی که هر کاربر در یک روز می‌تواند پاسخ و تشریحی‌اش را ببیند.
       رسیدن به سقف، هشدار «answer_cap» برای مدیر می‌سازد. */
    'answer_daily_cap' => (int) env('ZABAN_ANSWER_DAILY_CAP', 200),

    /* حداکثر کلمه‌ی متفاوتی که هر کاربر در یک روز جزئیاتش (مثال‌ها) را می‌گیرد.
       بانک دیگر یک‌جا فرستاده نمی‌شود (امنیت محتوا، قدم ۱). رسیدن به سقف ← هشدار «word_cap». */
    'word_daily_cap' => (int) env('ZABAN_WORD_DAILY_CAP', 150),

    /* معنی‌ها هم کلمه‌به‌کلمه (قدم ۳). «مطالعه‌ی ترتیبی» هر بار معنی ۲۰۰–۳۰۰ کلمه‌ی یک
       دفترچه را نشان می‌دهد؛ این پیش‌فرض‌ها برای خواندن چند دفترچه در روز جا دارند.
       همه از پنل مدیریت (قیمت و تنظیمات ← امنیت محتوا) قابل تغییرند. */
    'meaning_daily_cap'   => (int) env('ZABAN_MEANING_DAILY_CAP', 1500),
    'lock_meanings_10min' => (int) env('ZABAN_LOCK_MEANINGS_10MIN', 900),
    'list_meanings'       => true,          // فهرست‌ها با معنی (پنل می‌تواند خاموشش کند)

    /* هر حساب فقط روی یک دستگاه: ورود تازه، نشست قبلی را خارج می‌کند. */
    'single_session' => (bool) env('ZABAN_SINGLE_SESSION', true),

    /* سقف کارت تازه‌ی هر روز در «مرور امروز» — پیش‌فرض پلتفرم؛ مدیر در پنل و هر
       دانشجو در پروفایل خودش می‌تواند عوضش کند (۵ تا ۱۰۰). */
    'new_per_day' => (int) env('ZABAN_NEW_PER_DAY', 20),

    /* ---------- قفل خودکار (امنیت محتوا، قدم ۲) ----------
       رفتاری که از انسان سر نمی‌زند ← حساب lock_hours ساعت قفل و هشدار «auto_lock».
       ادمین‌ها (admin/manager/editor) قفل نمی‌شوند. باز کردن: پنل ← هشدارهای امنیتی. */
    'lock_hours'          => (int) env('ZABAN_LOCK_HOURS', 24),
    'lock_words_10min'    => (int) env('ZABAN_LOCK_WORDS_10MIN', 100),    // کلمه‌ی متفاوت در ۱۰ دقیقه
    'lock_answers_10min'  => (int) env('ZABAN_LOCK_ANSWERS_10MIN', 100),  // پاسخ «آزاد» در ۱۰ دقیقه
    'lock_cap_streak'     => (int) env('ZABAN_LOCK_CAP_STREAK', 3),       // روزهای پیاپی رسیدن به سقف کلمه

    /* اگر یک حساب در یک روز این‌قدر بار دستگاه عوض کند، هشدار «account_sharing» */
    'sharing_alert_kicks' => (int) env('ZABAN_SHARING_ALERT_KICKS', 5),
];
