-- Upgrade utm_presets for per-user presets and full UTM support.
-- Idempotent: safe to re-run.

-- 1. Add user_id if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'user_id');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `user_id` INT(11) NOT NULL AFTER `id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add utm_term if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_term');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_term` VARCHAR(100) DEFAULT NULL AFTER `utm_campaign`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add utm_content if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_content');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_content` VARCHAR(100) DEFAULT NULL AFTER `utm_term`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Add user_id index if missing
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND INDEX_NAME = 'user_id');
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `utm_presets` ADD KEY `user_id` (`user_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Upgrade utm_presets for per-user presets and full UTM support.
-- Idempotent: safe to re-run.

-- 1. Add user_id if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'user_id');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `user_id` INT(11) NOT NULL AFTER `id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add utm_term if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_term');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_term` VARCHAR(100) DEFAULT NULL AFTER `utm_campaign`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add utm_content if missing
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND COLUMN_NAME = 'utm_content');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `utm_presets` ADD COLUMN `utm_content` VARCHAR(100) DEFAULT NULL AFTER `utm_term`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Add user_id index if missing
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'utm_presets'
    AND INDEX_NAME = 'user_id');
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `utm_presets` ADD KEY `user_id` (`user_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
