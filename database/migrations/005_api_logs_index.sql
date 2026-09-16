-- Composite index for fast rate-limit counting.
-- Idempotent.

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_request_logs'
    AND INDEX_NAME = 'idx_key_time');

SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `api_request_logs` ADD INDEX `idx_key_time` (`api_key_id`, `created_at`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;