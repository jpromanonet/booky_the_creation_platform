<?php

declare(strict_types=1);

final class UserService
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, email, role, is_active, last_login_at, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT id, name, email, role, is_active, last_login_at, created_at
             FROM users ORDER BY name'
        )->fetchAll() ?: [];
    }

    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(string $name, string $email, string $password, string $role, bool $active): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $role = $role === 'admin' ? 'admin' : 'lector';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Nombre y email válidos son obligatorios.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.'];
        }
        $dup = Database::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $dup->execute(['email' => $email]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'Ese email ya está en uso.'];
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :hash, :role, :active)'
        )->execute([
            'name' => $name,
            'email' => $email,
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => $active ? 1 : 0,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function update(int $id, string $name, string $email, string $role, bool $active, string $password = ''): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $role = $role === 'admin' ? 'admin' : 'lector';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Nombre y email válidos son obligatorios.'];
        }
        $dup = Database::pdo()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $dup->execute(['email' => $email, 'id' => $id]);
        if ($dup->fetch()) {
            return ['ok' => false, 'error' => 'Ese email ya está en uso.'];
        }
        Database::pdo()->prepare(
            'UPDATE users SET name = :name, email = :email, role = :role, is_active = :active WHERE id = :id'
        )->execute([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'active' => $active ? 1 : 0,
            'id' => $id,
        ]);
        if ($password !== '') {
            if (strlen($password) < 6) {
                return ['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
            }
            Database::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute([
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $id,
            ]);
        }
        if (Auth::user() && (int) Auth::user()['id'] === $id) {
            Auth::refreshSessionUser();
        }
        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function updateProfile(int $id, string $name, string $email): array
    {
        $row = self::find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }
        return self::update($id, $name, $email, (string) $row['role'], (bool) $row['is_active']);
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public static function changePassword(int $id, string $current, string $new, string $confirm): array
    {
        if (strlen($new) < 6) {
            return ['ok' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'La confirmación no coincide.'];
        }

        $stmt = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $hash = (string) $stmt->fetchColumn();
        if ($hash === '' || !password_verify($current, $hash)) {
            return ['ok' => false, 'error' => 'La contraseña actual no es correcta.'];
        }

        $upd = Database::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $upd->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $id]);
        return ['ok' => true];
    }

    /** @return list<int> */
    public static function bookIds(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT book_id FROM user_books WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @param list<int> $bookIds */
    public static function syncBooks(int $userId, array $bookIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM user_books WHERE user_id = :u')->execute(['u' => $userId]);
        $ins = $pdo->prepare('INSERT INTO user_books (user_id, book_id) VALUES (:u, :b)');
        foreach (array_unique($bookIds) as $bookId) {
            if ($bookId > 0) {
                $ins->execute(['u' => $userId, 'b' => $bookId]);
            }
        }
    }
}
