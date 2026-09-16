-- Webhooks + delivery log for v1.1.0.
-- Idempotent: safe to re-run.

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `secret` VARCHAR(64) NOT NULL,
  `events` VARCHAR(255) NOT NULL DEFAULT 'link.created',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `last_fired_at` DATETIME DEFAULT NULL,
  `failure_count` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` INT(11) NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `payload` TEXT NOT NULL,
  `response_code` INT(11) DEFAULT NULL,
  `response_body` VARCHAR(500) DEFAULT NULL,
  `attempt` TINYINT(3) NOT NULL DEFAULT 1,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `error` VARCHAR(255) DEFAULT NULL,
  `next_retry_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `webhook_id` (`webhook_id`),
  KEY `status` (`status`),
  KEY `next_retry_at` (`next_retry_at`),
  CONSTRAINT `webhook_deliveries_ibfk_1` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;