# Migrations

## Rules

1. **Naming:** `NNN_description.sql` where NNN is a zero-padded 3-digit number.
   - Examples: `001_initial_schema.sql`, `005_api_logs_index.sql`
   - Never reuse a number. Never edit an existing migration.

2. **One logical change per file.** Don't combine a table creation with an index change.

3. **Idempotency is mandatory.** Every migration must be safe to run twice.
   - Use `CREATE TABLE IF NOT EXISTS`
   - Use `ADD COLUMN IF NOT EXISTS`
   - Use `INSERT IGNORE INTO` for seed data
   - Wrap complex ALTERs in `information_schema` checks (see below)

4. **Never touch user data destructively.** Don't `DROP COLUMN` or `DROP TABLE`
   without a very good reason and a backup plan.

## Idempotency patterns

### Simple column add
\`\`\`sql
ALTER TABLE `links` ADD COLUMN IF NOT EXISTS `preview_image` VARCHAR(255) DEFAULT NULL;
\`\`\`

### Conditional column add (for MySQL 5.7 compat)
\`\`\`sql
SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'links'
    AND COLUMN_NAME = 'preview_image');
SET @sql := IF(@has = 0,
  'ALTER TABLE links ADD COLUMN preview_image VARCHAR(255) DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
\`\`\`

### Conditional index add
\`\`\`sql
SET @has := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'api_request_logs'
    AND INDEX_NAME = 'idx_key_time');
SET @sql := IF(@has = 0,
  'ALTER TABLE api_request_logs ADD INDEX idx_key_time (api_key_id, created_at)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
\`\`\`

### Seed data
\`\`\`sql
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('setting_a', 'value_a'),
('setting_b', 'value_b');
\`\`\`

## How migrations run

`App\Core\MigrationRunner::run()` is called:
- On fresh installs (from `InstallController`)
- On every update (from `UpdateController`)
- Automatically in local dev (from `public/index.php` when host starts with `localhost`)

It reads every `.sql` file in this folder, checks the `schema_migrations` table,
and runs any that aren't recorded yet. Each file is recorded only after it
succeeds. If a file fails, the entire chain aborts — nothing else runs.

## Testing a new migration

1. Add the `.sql` file to this folder.
2. Refresh any LinkForge page in local dev — it runs automatically.
3. Check phpMyAdmin → `schema_migrations` → your file should be listed.
4. To test idempotency, delete its row from `schema_migrations` and refresh again.
   The migration should re-run without errors.

## Rollback

There is no automatic rollback. If a migration causes trouble:

1. Restore the DB from `storage/backups/` (auto-created before each update).
2. Delete the migration file from this folder.
3. Delete its row from `schema_migrations`.
4. Deploy.