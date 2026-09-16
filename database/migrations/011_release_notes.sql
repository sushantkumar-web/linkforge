-- Release notes tracking for the "What's New" modal.
-- Idempotent.

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('release_notes_seen_version', '');