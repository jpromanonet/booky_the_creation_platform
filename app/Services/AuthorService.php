<?php

declare(strict_types=1);

final class AuthorService
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM authors WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $ids = AccessService::allowedBookIds();
        if (Auth::isAdmin()) {
            return Database::pdo()->query(
                'SELECT a.*, (SELECT COUNT(*) FROM books b WHERE b.author_id = a.id) AS book_count
                 FROM authors a ORDER BY a.name'
            )->fetchAll() ?: [];
        }
        if ($ids === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        return Database::pdo()->query(
            "SELECT DISTINCT a.*, (SELECT COUNT(*) FROM books b WHERE b.author_id = a.id AND b.id IN ($in)) AS book_count
             FROM authors a
             INNER JOIN books b ON b.author_id = a.id
             WHERE b.id IN ($in)
             ORDER BY a.name"
        )->fetchAll() ?: [];
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(string $name, string $bio, string $country): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'El nombre del autor es obligatorio.'];
        }
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO authors (name, bio, country) VALUES (:name, :bio, :country)')->execute([
            'name' => $name,
            'bio' => trim($bio) ?: null,
            'country' => trim($country) ?: null,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function update(int $id, string $name, string $bio, string $country): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'error' => 'El nombre del autor es obligatorio.'];
        }
        Database::pdo()->prepare(
            'UPDATE authors SET name = :name, bio = :bio, country = :country WHERE id = :id'
        )->execute([
            'name' => $name,
            'bio' => trim($bio) ?: null,
            'country' => trim($country) ?: null,
            'id' => $id,
        ]);
        return ['ok' => true];
    }

    public static function delete(int $id): void
    {
        $books = Database::pdo()->prepare('SELECT id FROM books WHERE author_id = :id');
        $books->execute(['id' => $id]);
        foreach ($books->fetchAll(PDO::FETCH_COLUMN) ?: [] as $bookId) {
            BookService::delete((int) $bookId);
        }
        Database::pdo()->prepare('DELETE FROM authors WHERE id = :id')->execute(['id' => $id]);
    }
}
