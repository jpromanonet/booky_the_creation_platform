<?php

declare(strict_types=1);

/**
 * Instalación one-shot. Abrí /install.php una vez y después borralo.
 */

require_once __DIR__ . '/config/env.php';

header('Content-Type: text/html; charset=utf-8');

$host = booky_env('DB_HOST', '127.0.0.1');
$port = (int) booky_env('DB_PORT', '3306');
$name = booky_env('DB_NAME', 'booky');
$user = booky_env('DB_USER', 'root');
$pass = booky_env('DB_PASS', '') ?? '';
$sqlFile = __DIR__ . '/databases/booky.sql';

$ok = false;
$error = null;

try {
    if (!is_file($sqlFile)) {
        throw new RuntimeException('No está databases/booky.sql');
    }
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sql = (string) file_get_contents($sqlFile);
    $pdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '', $name)
    ));
    $pdo->exec('USE `' . str_replace('`', '', $name) . '`');

    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($tables) {
        $ok = true;
        $error = 'La base ya tenía tablas. No se volvió a importar.';
    } else {
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
        $ok = true;
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Instalar Booky</title>
    <style>
        body { font-family: sans-serif; max-width: 40rem; margin: 4rem auto; color: #1a202c; }
        .ok { color: #9a3412; }
        .err { color: #b91c1c; }
        a { color: #9a3412; }
    </style>
</head>
<body>
    <h1>Booky</h1>
    <?php if ($ok): ?>
        <p class="ok"><?= htmlspecialchars($error && str_contains($error, 'ya tenía') ? $error : 'Base importada.', ENT_QUOTES, 'UTF-8') ?></p>
        <p>Login: <code>admin@booky.local</code> / <code>changeme</code></p>
        <p><a href="index.php">Entrar</a> · borré o protegés <code>install.php</code> después.</p>
    <?php else: ?>
        <p class="err"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
</body>
</html>
