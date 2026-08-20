<?php

declare(strict_types=1);

$appConfig = require dirname(__DIR__) . '/config/app.php';
$dbConfig = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Services/Schema.php';
require_once __DIR__ . '/Services/UserService.php';
require_once __DIR__ . '/Services/AccessService.php';
require_once __DIR__ . '/Services/AuthorService.php';
require_once __DIR__ . '/Services/PageCounter.php';
require_once __DIR__ . '/Services/DocumentService.php';
require_once __DIR__ . '/Services/BookService.php';
require_once __DIR__ . '/Services/DocumentText.php';
require_once __DIR__ . '/Services/SimplePdf.php';
require_once __DIR__ . '/Services/PdfMergeService.php';
require_once __DIR__ . '/Services/ManuscriptPdfService.php';
require_once __DIR__ . '/Services/ProgressService.php';
require_once __DIR__ . '/Services/MilestoneCoachService.php';

foreach ([
    'AuthController',
    'DashboardController',
    'MilestoneController',
    'AuthorController',
    'BookController',
    'PdfController',
    'UserController',
    'SettingsController',
] as $controller) {
    require_once __DIR__ . '/Controllers/' . $controller . '.php';
}

if (PHP_SAPI !== 'cli') {
    Auth::startSession($appConfig['session_name']);
    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        $env = (string) ($appConfig['env'] ?? 'local');
        if (in_array($env, ['production', 'prod'], true)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

try {
    Database::connect($dbConfig);
    if (PHP_SAPI !== 'cli') {
        Schema::ensure();
    }
} catch (Throwable $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'DB error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Booky</title></head><body>';
    echo '<h1>Booky</h1><p>No se pudo conectar a la base de datos.</p>';
    if ($appConfig['debug']) {
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<p>Importá <code>databases/booky.sql</code> o abrí <code>install.php</code> y configurá <code>.env</code>.</p>';
    }
    echo '</body></html>';
    exit;
}

return [
    'app' => $appConfig,
    'db' => $dbConfig,
];
