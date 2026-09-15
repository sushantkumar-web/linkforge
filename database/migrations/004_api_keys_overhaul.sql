-- 1. Overhaul api_keys table
ALTER TABLE `api_keys` 
DROP COLUMN `api_key`,
ADD COLUMN `name` VARCHAR(100) NOT NULL AFTER `user_id`,
ADD COLUMN `key_prefix` VARCHAR(20) NOT NULL AFTER `name`,
ADD COLUMN `hashed_key` VARCHAR(64) NOT NULL AFTER `key_prefix`,
ADD COLUMN `scopes` VARCHAR(255) DEFAULT 'links:read' AFTER `hashed_key`,
ADD COLUMN `rate_limit_rpm` INT(11) DEFAULT 60 AFTER `scopes`,
ADD COLUMN `total_requests` INT(11) DEFAULT 0 AFTER `rate_limit_rpm`,
ADD COLUMN `status` VARCHAR(20) DEFAULT 'active' AFTER `total_requests`,
ADD COLUMN `last_used_at` DATETIME NULL AFTER `status`,
ADD UNIQUE KEY `hashed_key` (`hashed_key`),
ADD KEY `key_prefix` (`key_prefix`);

-- 2. Create telemetry logs table
CREATE TABLE `api_request_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `api_key_id` INT(11) NOT NULL,
  `endpoint` VARCHAR(255) NOT NULL,
  `http_method` VARCHAR(10) NOT NULL,
  `status_code` INT(11) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `api_key_id` (`api_key_id`),
  CONSTRAINT `api_request_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Prep users table for RBAC
ALTER TABLE `users` 
ADD COLUMN `last_login_at` DATETIME NULL AFTER `updated_at`,
MODIFY COLUMN `role` VARCHAR(50) DEFAULT 'user';