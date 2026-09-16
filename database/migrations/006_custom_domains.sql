-- Custom domains for v1.1.0.
-- Idempotent: safe to re-run.

CREATE TABLE IF NOT EXISTS `domains` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `hostname` VARCHAR(255) NOT NULL,
  `verification_token` VARCHAR(64) NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  KEY `user_id` (`user_id`),
  KEY `verified_at` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add domain_id to links. NULL means "use the default installed host".
ALTER TABLE `links`
  ADD COLUMN IF NOT EXISTS `domain_id` INT(11) DEFAULT NULL AFTER `user_id`,
  ADD KEY IF NOT EXISTS `idx_domain` (`domain_id`);

-- Composite index for the redirect lookup: (short_code, domain_id) — must be unique per domain
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'links'
    AND INDEX_NAME = 'idx_short_domain');
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE `links` ADD INDEX `idx_short_domain` (`short_code`, `domain_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;