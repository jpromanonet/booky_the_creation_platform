<?php

declare(strict_types=1);

final class AccessService
{
    public static function canViewBook(int $bookId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM user_books WHERE user_id = :u AND book_id = :b LIMIT 1'
        );
        $stmt->execute(['u' => (int) $user['id'], 'b' => $bookId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function requireBook(int $bookId): void
    {
        Auth::requireLogin();
        if (!self::canViewBook($bookId)) {
            http_response_code(403);
            echo '403 — Este libro no está asignado a tu usuario.';
            exit;
        }
    }

    /** @return list<int> */
    public static function allowedBookIds(): array
    {
        if (Auth::isAdmin()) {
            $ids = Database::pdo()->query('SELECT id FROM books')->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $ids ?: []);
        }
        $user = Auth::user();
        if (!$user) {
            return [];
        }
        $stmt = Database::pdo()->prepare('SELECT book_id FROM user_books WHERE user_id = :u');
        $stmt->execute(['u' => (int) $user['id']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
