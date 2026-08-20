<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'host' => booky_env('DB_HOST', '127.0.0.1'),
    'port' => (int) booky_env('DB_PORT', '3306'),
    'name' => booky_env('DB_NAME', 'booky'),
    'user' => booky_env('DB_USER', 'root'),
    'pass' => booky_env('DB_PASS', '') ?? '',
    'charset' => 'utf8mb4',
];
