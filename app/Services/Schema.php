<?php

declare(strict_types=1);

final class Schema
{
    public static function ensure(): void
    {
        $pdo = Database::pdo();
        $sqlFile = dirname(__DIR__, 2) . '/databases/booky.sql';
        if (!is_file($sqlFile)) {
            return;
        }

        $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$tables) {
            $sql = (string) file_get_contents($sqlFile);
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                if (preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) {
                    continue;
                }
                $pdo->exec($stmt);
            }
        }

        self::migrate();
    }

    public static function migrate(): void
    {
        $pdo = Database::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS book_parts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                book_id INT UNSIGNED NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 1,
                title VARCHAR(220) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY book_parts_book (book_id, sort_order),
                CONSTRAINT book_parts_book_fk FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (!self::columnExists('chapters', 'kind')) {
            $pdo->exec(
                "ALTER TABLE chapters
                 ADD COLUMN kind ENUM('introduction','chapter','epilogue') NOT NULL DEFAULT 'chapter' AFTER title"
            );
        }
        if (!self::columnExists('chapters', 'part_id')) {
            $pdo->exec('ALTER TABLE chapters ADD COLUMN part_id INT UNSIGNED NULL AFTER book_id');
            $pdo->exec('ALTER TABLE chapters ADD KEY chapters_part (part_id)');
            try {
                $pdo->exec(
                    'ALTER TABLE chapters
                     ADD CONSTRAINT chapters_part_fk FOREIGN KEY (part_id) REFERENCES book_parts (id) ON DELETE SET NULL'
                );
            } catch (Throwable) {
                // Constraint may already exist on a previous partial migrate.
            }
        }

        try {
            $pdo->exec(
                "ALTER TABLE documents
                 MODIFY kind ENUM('outline','synopsis','chapter','pdf') NOT NULL"
            );
        } catch (Throwable) {
            // Already migrated or table missing on first install from SQL.
        }
    }

    private static function columnExists(string $table, string $column): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
