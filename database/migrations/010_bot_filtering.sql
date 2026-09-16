-- Bot filtering settings for v1.2.0.
-- Idempotent.

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('bot_filtering_enabled', '1');
