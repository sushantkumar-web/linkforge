-- Captcha settings for login / password reset protection.
-- Idempotent: uses INSERT IGNORE.

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('captcha_enabled',    '0'),
('captcha_provider',   'turnstile'),
('captcha_site_key',   ''),
('captcha_secret_key', '');