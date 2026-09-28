-- سقف مرور روزانه‌ی هر دانشجو. NULL یعنی «پیش‌فرض مدیر».
-- همان الگوی ستون new_per_day که از قبل در همین جدول هست.
ALTER TABLE `zaban_profiles`
  ADD COLUMN `rev_per_day` SMALLINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'سقف مرور روزانه؛ NULL = پیش‌فرض مدیر'
  AFTER `new_per_day`;
