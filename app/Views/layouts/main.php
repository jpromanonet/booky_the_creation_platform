<?php
/** @var string $templateFile */
/** @var string $appName */
/** @var string $title */
/** @var array|null $user */
$isAdmin = $user ? Auth::isAdmin() : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' . $appName : $appName) ?></title>
    <script>
    (function () {
      try {
        var t = localStorage.getItem('booky-theme');
        if (t !== 'dark' && t !== 'light') t = 'light';
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    })();
    </script>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/css/app.css') ?>">
</head>
<body class="<?= $isAdmin ? '' : ' is-readonly-user' ?>">
<?php if (!$user): ?>
<button type="button" class="theme-toggle theme-toggle--auth" aria-label="Cambiar a modo oscuro">
    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M21 14.3A8.5 8.5 0 0 1 9.7 3 7 7 0 1 0 21 14.3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
    </svg>
    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
        <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>
</button>
<?php endif; ?>
<?php if ($user): ?>
<header class="topbar" id="topbar">
    <div class="brand">
        <a href="<?= e(url('/')) ?>"><?= e($appName) ?></a>
    </div>
    <button type="button" class="theme-toggle theme-toggle--bar" aria-label="Cambiar a modo oscuro">
        <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 14.3A8.5 8.5 0 0 1 9.7 3 7 7 0 1 0 21 14.3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
        <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 2v2.2M12 19.8V22M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2 12h2.2M19.8 12H22M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
    </button>
    <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="topbar-panel" aria-label="Abrir menú">
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
        <span class="nav-toggle-bar" aria-hidden="true"></span>
    </button>
    <div class="topbar-panel" id="topbar-panel">
        <nav class="nav">
            <a class="<?= e(nav_active('/', true)) ?>" href="<?= e(url('/')) ?>">Dashboard</a>
            <a class="<?= e(nav_active('/milestones')) ?>" href="<?= e(url('/milestones')) ?>">Milestones</a>
            <a class="<?= e(nav_active('/libros')) ?>" href="<?= e(url('/libros')) ?>">Libros</a>
            <a class="<?= e(nav_active('/pdf')) ?>" href="<?= e(url('/pdf')) ?>">PDF</a>
            <a class="<?= e(nav_active('/autores')) ?>" href="<?= e(url('/autores')) ?>">Autores</a>
            <?php if ($isAdmin): ?>
                <a class="<?= e(nav_active('/usuarios')) ?>" href="<?= e(url('/usuarios')) ?>">Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="userbox">
            <details class="nav-drop user-drop<?= nav_active('/configuracion') ? ' is-active' : '' ?>">
                <summary><?= e($user['name'] ?? 'Perfil') ?></summary>
                <div class="nav-menu">
                    <a class="<?= e(nav_active('/configuracion')) ?>" href="<?= e(url('/configuracion')) ?>">Configuración</a>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="nav-menu-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-menu-btn">Salir</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
<?php endif; ?>

<main class="<?= $user ? 'shell' : 'auth-shell' ?>">
    <?php require $templateFile; ?>
</main>

<footer class="footer">
    <span><?= e($appName) ?> · acompañamiento de escritura</span>
</footer>
<button type="button" class="back-to-top" id="back-to-top" aria-label="Volver arriba">↑</button>
<script src="<?= e(url('/assets/js/chart.umd.min.js')) ?>" defer></script>
<script src="<?= e(url('/assets/js/app.js')) ?>?v=<?= (int) @filemtime(dirname(__DIR__, 3) . '/assets/js/app.js') ?>" defer></script>
</body>
</html>
