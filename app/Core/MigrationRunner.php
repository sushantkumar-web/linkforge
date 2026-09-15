<?php
namespace App\Core;

use PDO;
use Exception;

class MigrationRunner {

    /**
     * Run all pending migrations from database/migrations/.
     * Returns ['applied' => [...], 'skipped' => [...]].
     */
    public static function run(): array {
        $pdo = Database::getInstance();
        $migrationsDir = BASE_PATH . '/database/migrations';

        if (!is_dir($migrationsDir)) {
            return ['applied' => [], 'skipped' => []];
        }

        // 1. Ensure tracking table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(255) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // 2. Load what's already applied
        $applied = $pdo->query("SELECT migration FROM schema_migrations")
                       ->fetchAll(PDO::FETCH_COLUMN);
        $applied = array_flip($applied);

        // 3. Find all migration files, sort alphabetically
        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        $newlyApplied = [];
        $skipped = [];

        foreach ($files as $file) {
            $filename = basename($file);

            if (isset($applied[$filename])) {
                $skipped[] = $filename;
                continue;
            }

            $sql = file_get_contents($file);
            if (trim($sql) === '') {
                self::mark($pdo, $filename);
                $newlyApplied[] = $filename;
                continue;
            }

            try {
                $pdo->exec($sql);
                self::mark($pdo, $filename);
                $newlyApplied[] = $filename;
            } catch (Exception $e) {
                // Abort on error. Do NOT mark as applied. Do NOT continue.
                throw new Exception("Migration '{$filename}' failed: " . $e->getMessage());
            }
        }

        return ['applied' => $newlyApplied, 'skipped' => $skipped];
    }

    private static function mark(PDO $pdo, string $filename): void {
        $stmt = $pdo->prepare("INSERT IGNORE INTO schema_migrations (migration) VALUES (?)");
        $stmt->execute([$filename]);
    }
}