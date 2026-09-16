-- Link targeting (device + country) for v1.1.0.
-- Idempotent: safe to re-run.

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'links'
    AND COLUMN_NAME = 'targeting');

SET @sql := IF(@has_col = 0,
  'ALTER TABLE `links` ADD COLUMN `targeting` TEXT DEFAULT NULL AFTER `fallback_url`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;