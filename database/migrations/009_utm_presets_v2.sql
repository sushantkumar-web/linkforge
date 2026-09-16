-- Upgrade utm_presets for per-user presets and full UTM support.
-- Fully idempotent: creates the table if missing, then adds columns conditionally.
-- Works on both fresh installs and existing installs that never had the table.

-- 1. Create the table if it doesn't exist at all
CREATE TABLE IF NOT EXISTS `utm_presets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `utm_source` VARCHAR(100) NOT NULL,
  `utm_medium` VARCHAR(100) NOT NULL,
  `utm_campaign` VARCHAR(100) DEFAULT NULL,
  `utm_term` VARCHAR(100) DEFAULT NULL,
  `utm_content` VARCHAR(100) DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Add user_id if missing (for installs with the old 6-column schema)
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'user_id');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `user_id` INT(11) NOT NULL AFTER `id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add utm_term if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_term');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_term` VARCHAR(100) DEFAULT NULL AFTER `utm_campaign`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Add utm_content if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_content');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_content` VARCHAR(100) DEFAULT NULL AFTER `utm_term`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Add user_id index if missing
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND INDEX_NAME = 'user_id');
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `utm_presets` ADD KEY `user_id` (`user_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;