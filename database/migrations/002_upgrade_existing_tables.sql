-- Upgrade existing v1.0.2 installations to v1.0.3 schema.
-- Fully idempotent: every statement uses IF NOT EXISTS or information_schema checks.
-- No-op on fresh installs where 001 already created the current schema.

-- ============================================================
-- USERS: add missing columns for existing installs
-- ============================================================
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role` VARCHAR(50) DEFAULT 'user',
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME DEFAULT NULL;

-- Migrate password_hash → password if the old column exists
SET @has_old := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash');
SET @has_new := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password');

SET @sql := IF(@has_new = 0 AND @has_old > 0,
  'ALTER TABLE `users` CHANGE COLUMN `password_hash` `password` VARCHAR(255) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- LINKS: add fallback_url for existing installs
-- ============================================================
ALTER TABLE `links`
  ADD COLUMN IF NOT EXISTS `fallback_url` VARCHAR(2048) DEFAULT NULL AFTER `destination_url`;

-- ============================================================
-- API_KEYS: convert old raw-key schema to hashed-key schema
-- ============================================================

-- Drop the old raw key column if it exists
SET @has_old := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND COLUMN_NAME = 'api_key');
SET @sql := IF(@has_old > 0, 'ALTER TABLE `api_keys` DROP COLUMN `api_key`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add the new columns
ALTER TABLE `api_keys`
  ADD COLUMN IF NOT EXISTS `name` VARCHAR(100) NOT NULL DEFAULT 'Legacy Key' AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `key_prefix` VARCHAR(20) NOT NULL DEFAULT '' AFTER `name`,
  ADD COLUMN IF NOT EXISTS `hashed_key` VARCHAR(64) NOT NULL DEFAULT '' AFTER `key_prefix`,
  ADD COLUMN IF NOT EXISTS `scopes` VARCHAR(255) DEFAULT 'links:read' AFTER `hashed_key`,
  ADD COLUMN IF NOT EXISTS `rate_limit_rpm` INT(11) DEFAULT 60 AFTER `scopes`,
  ADD COLUMN IF NOT EXISTS `total_requests` INT(11) DEFAULT 0 AFTER `rate_limit_rpm`,
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) DEFAULT 'active' AFTER `total_requests`;

-- Add indexes for the new columns
SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND INDEX_NAME = 'hashed_key');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE `api_keys` ADD UNIQUE KEY `hashed_key` (`hashed_key`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_keys' AND INDEX_NAME = 'key_prefix');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE `api_keys` ADD KEY `key_prefix` (`key_prefix`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;