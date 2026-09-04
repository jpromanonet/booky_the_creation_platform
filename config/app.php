<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'name' => booky_env('APP_NAME', 'Booky'),
    'env' => strtolower((string) booky_env('APP_ENV', 'local')),
    'debug' => filter_var(booky_env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) booky_env('APP_URL', ''), '/'),
    'session_name' => (string) booky_env('SESSION_NAME', 'booky_session'),
    'session_lifetime' => (int) booky_env('SESSION_LIFETIME', '86400'),
    'session_idle' => (int) booky_env('SESSION_IDLE', '86400'),
    'timezone' => 'America/Argentina/Buenos_Aires',
];
