-- Default email configuration keys.
-- Uses INSERT IGNORE so existing values are never overwritten.

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('mail_driver',  'log'),
('smtp_host',    ''),
('smtp_port',    '587'),
('smtp_user',    ''),
('smtp_pass',    ''),
('smtp_secure',  'tls'),
('from_email',   ''),
('from_name',    'LinkForge');