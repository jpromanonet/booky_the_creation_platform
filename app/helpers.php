<?php

declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $appUrl = (string) app_config('url', '');
    if ($appUrl !== '') {
        $urlPath = parse_url($appUrl, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            $cached = rtrim($urlPath, '/');
            return $cached;
        }
        $cached = '';
        return $cached;
    }

    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '/' || $script === '\\' || $script === '.') {
        $cached = '';
        return $cached;
    }
    $cached = rtrim($script, '/');
    return $cached;
}

function url(string $path = '/'): string
{
    $extraQuery = [];
    $hash = '';
    if (str_contains($path, '#')) {
        [$path, $hash] = explode('#', $path, 2);
        $hash = '#' . $hash;
    }
    if (str_contains($path, '?')) {
        [$path, $qs] = explode('?', $path, 2);
        parse_str($qs, $extraQuery);
    }

    $path = '/' . ltrim($path, '/');
    if ($path === '//') {
        $path = '/';
    }

    $base = base_path();

    if (str_starts_with($path, '/assets/') || preg_match('#^/[^/]+\.php$#', $path) === 1) {
        $suffix = $extraQuery ? ('?' . http_build_query($extraQuery)) : '';
        return $base . $path . $suffix . $hash;
    }

    $script = $base . '/index.php';
    $params = $extraQuery;
    if ($path !== '/') {
        $params = array_merge(['r' => $path], $params);
    }
    $suffix = $params ? ('?' . http_build_query($params)) : '';
    return $script . $suffix . $hash;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function view(string $template, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    $appName = (string) app_config('name', 'Booky');
    $user = Auth::user();
    $templateFile = dirname(__DIR__) . '/app/Views/' . $template . '.php';
    if (!is_file($templateFile)) {
        throw new RuntimeException('View not found: ' . $template);
    }
    require dirname(__DIR__) . '/app/Views/layouts/main.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $token);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function require_csrf(): void
{
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        flash('error', 'Sesión inválida. Probá de nuevo.');
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $appUrl = (string) app_config('url', '');
        if ($appUrl !== '' && $ref !== '' && str_starts_with($ref, $appUrl)) {
            header('Location: ' . $ref);
            exit;
        }
        redirect('/');
    }
}

function selected(mixed $current, mixed $value): string
{
    return (string) $current === (string) $value ? ' selected' : '';
}

function checked(mixed $current, bool|int $flag = true): string
{
    return ((bool) $current === (bool) $flag) ? ' checked' : '';
}

function post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function current_route_path(): string
{
    if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
        $path = '/' . trim($_GET['r'], '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    foreach (['PATH_INFO', 'ORIG_PATH_INFO'] as $key) {
        $info = $_SERVER[$key] ?? '';
        if (is_string($info) && $info !== '') {
            $path = '/' . trim($info, '/');
            return $path === '/' ? '/' : rtrim($path, '/');
        }
    }

    return '/';
}

function nav_active(string $prefix, bool $exact = false): string
{
    $path = current_route_path();
    if ($exact || $prefix === '/') {
        return $path === $prefix ? ' is-active' : '';
    }
    return ($path === $prefix || str_starts_with($path, $prefix . '/')) ? ' is-active' : '';
}

function format_n(int|float|string|null $n, int $decimals = 0): string
{
    return number_format((float) $n, $decimals, ',', '.');
}

function format_pct(int|float|string|null $n, int $decimals = 1): string
{
    return number_format((float) $n, $decimals, ',', '.') . '%';
}

function format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }
    return date('d/m/Y H:i', $ts);
}

function role_label(string $role): string
{
    return $role === 'admin' ? 'Admin' : 'Lector';
}

function format_roman(int $n): string
{
    if ($n < 1) {
        return (string) $n;
    }
    $map = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
        10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
    ];
    $out = '';
    foreach ($map as $value => $glyph) {
        while ($n >= $value) {
            $out .= $glyph;
            $n -= $value;
        }
    }
    return $out;
}

function section_kind_label(string $kind): string
{
    return match ($kind) {
        'introduction' => 'Introducción',
        'epilogue' => 'Epílogo',
        default => 'Capítulo',
    };
}
