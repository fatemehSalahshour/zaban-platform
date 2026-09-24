/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `announcement_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcement_reads` (
  `user_id` bigint unsigned NOT NULL,
  `read_at` timestamp NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_published_at_index` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `answer_reveals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `answer_reveals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `question_id` int unsigned NOT NULL,
  `reason` enum('free','washback','finished') COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `rev_user_day` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `board_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `board_cache` (
  `period` enum('d1','d7','d30','d60','d90','d180','d365') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `points` int unsigned NOT NULL DEFAULT '0',
  `study_sec` int unsigned NOT NULL DEFAULT '0',
  `reviews` int unsigned NOT NULL DEFAULT '0',
  `reviews_ok` int unsigned NOT NULL DEFAULT '0',
  `questions` int unsigned NOT NULL DEFAULT '0',
  `questions_ok` int unsigned NOT NULL DEFAULT '0',
  `mastered` int unsigned NOT NULL DEFAULT '0',
  `rank_no` int unsigned DEFAULT NULL,
  `built_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`period`,`user_id`),
  KEY `board_rank` (`period`,`points` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_activity` (
  `user_id` int unsigned NOT NULL,
  `day` date NOT NULL,
  `study_sec` int unsigned NOT NULL DEFAULT '0' COMMENT 'حداکثر ۲۴۰ دقیقه در روز شمرده می‌شود',
  `reviews` smallint unsigned NOT NULL DEFAULT '0',
  `reviews_ok` smallint unsigned NOT NULL DEFAULT '0',
  `questions` smallint unsigned NOT NULL DEFAULT '0',
  `questions_ok` smallint unsigned NOT NULL DEFAULT '0',
  `mastered` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`day`),
  KEY `act_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deck_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deck_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_type` enum('word','question') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `ease_factor` decimal(4,2) NOT NULL DEFAULT '2.50',
  `interval_days` smallint unsigned NOT NULL DEFAULT '0',
  `reps` smallint unsigned NOT NULL DEFAULT '0',
  `lapses` smallint unsigned NOT NULL DEFAULT '0',
  `due_date` date NOT NULL,
  `last_review_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `seen_count` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'کل مرورها',
  `again_count` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'کل «یادم نبود»',
  `mastered_at` date DEFAULT NULL COMMENT 'اولین باری که بازه به ۲۱ روز رسید',
  `stability` decimal(10,4) DEFAULT NULL COMMENT 'S — روز تا افت احتمال یادآوری به ۹۰٪',
  `difficulty` decimal(5,2) DEFAULT NULL COMMENT 'D — بین ۱ و ۱۰',
  `elapsed_days` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'فاصله‌ی مرور قبلی، برای محاسبه‌ی R',
  `scheduler` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fsrs6' COMMENT 'sm2 | fsrs6 — تا بشود تدریجی مهاجرت داد',
  PRIMARY KEY (`id`),
  UNIQUE KEY `deck_unique` (`user_id`,`item_type`,`item_id`),
  KEY `deck_due_date` (`user_id`,`due_date`),
  KEY `deck_mastered` (`user_id`,`mastered_at`),
  KEY `deck_due` (`user_id`,`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_answers` (
  `attempt_id` bigint unsigned NOT NULL,
  `question_id` int unsigned NOT NULL,
  `chosen` tinyint unsigned DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `flagged` tinyint(1) NOT NULL DEFAULT '0',
  `eliminated` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'بیت‌مسک رد گزینه: ۱|۲|۴|۸',
  PRIMARY KEY (`attempt_id`,`question_id`),
  KEY `ans_q` (`question_id`),
  CONSTRAINT `fk_ans_att` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ans_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mode` enum('feedback','washback') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'feedback',
  `duration_sec` int unsigned NOT NULL DEFAULT '0',
  `started_at` datetime NOT NULL,
  `deadline_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `auto_closed` tinyint(1) NOT NULL DEFAULT '0',
  `total_q` tinyint unsigned NOT NULL DEFAULT '0',
  `correct` tinyint unsigned NOT NULL DEFAULT '0',
  `wrong` tinyint unsigned NOT NULL DEFAULT '0',
  `blank` tinyint unsigned NOT NULL DEFAULT '0',
  `percent` decimal(5,2) DEFAULT NULL COMMENT '(۳×درست − غلط) ÷ (۳×کل) — می‌تواند منفی باشد',
  `used_sec` int unsigned DEFAULT NULL,
  `state_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `att_user` (`user_id`,`year`,`exam`),
  KEY `att_open` (`user_id`,`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_sections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('vocab','cloze','passage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `passage_number` tinyint unsigned DEFAULT NULL COMMENT 'فقط برای پسیج',
  `from_question` tinyint unsigned NOT NULL,
  `to_question` tinyint unsigned NOT NULL,
  `sort_order` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'ترتیب نمایش در دفترچه',
  PRIMARY KEY (`id`),
  KEY `sec_year` (`year`,`exam`,`sort_order`),
  KEY `sec_range` (`year`,`exam`,`from_question`,`to_question`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_texts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_texts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('cloze','passage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `passage_number` tinyint unsigned DEFAULT NULL,
  `title` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_fa` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'ترجمه — اختیاری',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `txt_slot` (`year`,`exam`,`section`,`passage_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `meaning_reveals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `meaning_reveals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `word_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meaning_reveals_user_id_created_at_index` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `chosen` tinyint unsigned NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_attempts_user_id_question_id_index` (`user_id`,`question_id`),
  KEY `question_attempts_question_id_index` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_options` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int unsigned NOT NULL,
  `position` tinyint unsigned NOT NULL COMMENT '۱ تا ۴',
  `body` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `word_id` int unsigned DEFAULT NULL COMMENT 'اگر گزینه خودش کلمه‌ی بانک باشد',
  PRIMARY KEY (`id`),
  UNIQUE KEY `opt_unique` (`question_id`,`position`),
  KEY `opt_word` (`word_id`),
  CONSTRAINT `fk_opt_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_words` (
  `question_id` int unsigned NOT NULL,
  `word_id` int unsigned NOT NULL,
  `role` enum('answer','option','stem','passage') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`question_id`,`word_id`,`role`),
  KEY `qw_word` (`word_id`),
  CONSTRAINT `fk_qw_q` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qw_w` FOREIGN KEY (`word_id`) REFERENCES `words` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `question_number` tinyint unsigned NOT NULL,
  `section` enum('vocab','cloze','passage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `passage_number` tinyint unsigned DEFAULT NULL,
  `text_id` int unsigned DEFAULT NULL COMMENT 'با finalize وصل می‌شود',
  `stem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `stem_fa` text COLLATE utf8mb4_unicode_ci,
  `explanation` text COLLATE utf8mb4_unicode_ci COMMENT 'پاسخ تشریحی — فقط بعد از اتمام آزمون',
  `correct_option` tinyint unsigned NOT NULL COMMENT '۱ تا ۴',
  `opt_view` tinyint unsigned NOT NULL DEFAULT '3' COMMENT 'چیدمان گزینه‌ها: ۳=چهارتایی ۶=دوتایی ۱۲=تک‌ستونی',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `q_unique` (`year`,`exam`,`question_number`),
  KEY `q_exam` (`exam`,`year`),
  KEY `q_text` (`text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reading_marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_marks` (
  `user_id` int unsigned NOT NULL,
  `slot` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'قالب: <سال>|<رشته> — مثل 1403|ce',
  `position` smallint unsigned NOT NULL DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `report_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `by` enum('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `admin_id` bigint unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_messages_report_id_foreign` (`report_id`),
  CONSTRAINT `report_messages_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `item_type` enum('word','question') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `item_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('open','answered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `seen_at` timestamp NULL DEFAULT NULL,
  `last_msg_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reports_user_id_item_key_unique` (`user_id`,`item_key`),
  KEY `reports_state_last_msg_at_index` (`state`,`last_msg_at`),
  KEY `reports_item_type_item_id_index` (`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_type` enum('word','question') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `rating` tinyint unsigned NOT NULL COMMENT '۱ یادم نبود · ۲ سخت · ۳ یادم بود · ۴ ساده',
  `mode` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'deck',
  `interval_days` smallint unsigned NOT NULL DEFAULT '0',
  `ease_factor` decimal(4,2) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `stability` decimal(10,4) DEFAULT NULL,
  `difficulty` decimal(5,2) DEFAULT NULL,
  `retrievability` decimal(5,4) DEFAULT NULL COMMENT 'R در لحظه‌ی مرور — برای optimizer',
  `elapsed_days` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `log_user` (`user_id`,`created_at`),
  KEY `log_item` (`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `security_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `security_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `kind` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `seen_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_alerts_user_id_kind_day_unique` (`user_id`,`kind`,`day`),
  KEY `security_alerts_seen_at_created_at_index` (`seen_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_type` enum('word','question') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `note_unique` (`user_id`,`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_stars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_stars` (
  `user_id` int unsigned NOT NULL,
  `item_type` enum('word','question') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`,`item_type`,`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `global_uid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('student','teacher','operator','editor','manager','admin','print','test','trial') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sso_logout_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `session_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locked_until` timestamp NULL DEFAULT NULL,
  `lock_reason` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile`),
  UNIQUE KEY `users_global_uid_unique` (`global_uid`),
  KEY `users_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `word_examples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `word_examples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `word_id` int unsigned NOT NULL,
  `sentence` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sentence_fa` text COLLATE utf8mb4_unicode_ci,
  `sentence_hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'md5 جمله — کلید جلوگیری از تکرار',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ex_unique` (`word_id`,`sentence_hash`),
  CONSTRAINT `fk_ex_w` FOREIGN KEY (`word_id`) REFERENCES `words` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `word_occurrences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `word_occurrences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `word_id` int unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` enum('vocab','cloze','passage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `test_number` tinyint unsigned DEFAULT NULL COMMENT 'NULL یعنی داخل متن آمده، نه در سؤال',
  `passage_number` tinyint unsigned DEFAULT NULL,
  `slot` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مثل vocab:14 یا passage2:text',
  `source` enum('question','text') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'question',
  `question_id` int unsigned DEFAULT NULL COMMENT 'با finalize وصل می‌شود',
  PRIMARY KEY (`id`),
  UNIQUE KEY `occ_unique` (`word_id`,`year`,`exam`,`slot`),
  KEY `occ_exam` (`exam`,`year`),
  KEY `occ_q` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `word_reveals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `word_reveals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `word_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `word_reveals_user_id_created_at_index` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `words` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `word` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meaning_fa` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pos_raw` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pos_primary` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_phrase` tinyint(1) NOT NULL DEFAULT '0',
  `level` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'متوسط' COMMENT 'ساده | متوسط | پیشرفته',
  `form_base` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_past` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_participle` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_count` smallint unsigned NOT NULL DEFAULT '0',
  `occurrence_count` smallint unsigned NOT NULL DEFAULT '0',
  `first_year` smallint unsigned DEFAULT NULL,
  `last_year` smallint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pred_score` decimal(5,2) DEFAULT NULL COMMENT 'امتیاز مدل متوازن، ۰ تا ۹۷',
  `pred_rank` smallint unsigned DEFAULT NULL,
  `mean_gap` decimal(4,2) DEFAULT NULL COMMENT 'میانگین فاصله‌ی سال‌های حضور',
  `streak_recent` tinyint unsigned DEFAULT NULL COMMENT 'سال‌های پیاپی تا آخرین کنکور',
  `run_longest` tinyint unsigned DEFAULT NULL COMMENT 'بلندترین دوره‌ی پیاپی',
  `run_from` smallint unsigned DEFAULT NULL,
  `run_to` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `word_unique` (`word`),
  KEY `words_level` (`level`),
  KEY `words_last` (`last_year`),
  KEY `words_pred` (`pred_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zaban_entitlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zaban_entitlements` (
  `user_id` int unsigned NOT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` enum('purchase','manual','trial','staff') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'purchase',
  `order_id` bigint unsigned DEFAULT NULL,
  `granted_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL COMMENT 'NULL یعنی بی‌انتها؛ معمولاً روز کنکور',
  `revoked_at` datetime DEFAULT NULL COMMENT 'برگشت وجه یا لغو دستی — ردیف پاک نمی‌شود',
  `note` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`,`exam`),
  KEY `ent_live` (`user_id`,`revoked_at`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zaban_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zaban_meta` (
  `k` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `v` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zaban_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zaban_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `exams` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'مرتب‌شده و جداشده با کاما: ce,cs,it',
  `list_price` int unsigned NOT NULL COMMENT 'تومان — جمع بدون تخفیف',
  `discount` int unsigned NOT NULL DEFAULT '0',
  `payable` int unsigned NOT NULL COMMENT 'تومان — همین به درگاه می‌رود',
  `price_version` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'کدام جدول قیمت اعمال شده',
  `gateway` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authority` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شناسه‌ی درگاه هنگام شروع پرداخت',
  `ref_id` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'شماره‌ی پیگیری بعد از پرداخت موفق',
  `expires_at` datetime DEFAULT NULL COMMENT 'انقضای دسترسی که این سفارش می‌دهد',
  `created_at` timestamp NULL DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `request_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_rial` bigint unsigned DEFAULT NULL,
  `rrn` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stan` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `masked_pan` varchar(19) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `returned_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ord_authority` (`gateway`,`authority`),
  UNIQUE KEY `ord_ref` (`gateway`,`ref_id`),
  UNIQUE KEY `zaban_orders_request_id_unique` (`request_id`),
  UNIQUE KEY `zaban_orders_token_unique` (`token`),
  KEY `ord_user` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `zaban_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `zaban_profiles` (
  `user_id` int unsigned NOT NULL,
  `nickname` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exam` enum('ce','it','cs') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ce',
  `show_in_board` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `university` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gpa` decimal(4,2) DEFAULT NULL,
  `quota` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `degree` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_per_day` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `nick_unique` (`nickname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_08_24_000000_switch_to_fsrs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_09_08_120309_add_type_to_users',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_09_21_000001_create_reports_tables',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_09_21_000002_create_announcements_tables',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_09_21_000003_extend_zaban_profiles',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_09_21_000004_create_question_attempts_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_09_22_000001_add_sso_columns_to_users_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_09_21_000005_extend_zaban_orders_for_irankish',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_09_21_000006_widen_zaban_orders_status',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_09_22_000002_relax_zaban_profiles_nickname',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_09_22_000003_add_session_token_and_security_alerts',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_09_22_000004_create_word_reveals_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_09_22_000005_add_lock_to_users',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_09_22_000006_create_meaning_reveals_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_09_22_000007_add_new_per_day_to_profiles',15);
