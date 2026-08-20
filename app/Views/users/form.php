<?php
/** @var array<string,mixed>|null $account */
/** @var list<int> $assigned */
/** @var list<array<string,mixed>> $books */
$isEdit = !empty($account['id']);
$action = $isEdit ? '/usuarios/' . (int) $account['id'] : '/usuarios';
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Roles, permisos y libros asignados al lector.</p>
        </div>
        <a class="btn ghost" href="<?= e(url('/usuarios')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="post" action="<?= e(url($action)) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Nombre *
            <input name="name" required value="<?= e((string) ($account['name'] ?? '')) ?>">
        </label>
        <label>Email *
            <input type="email" name="email" required value="<?= e((string) ($account['email'] ?? '')) ?>">
        </label>
        <label>Rol *
            <select name="role">
                <option value="lector"<?= selected($account['role'] ?? 'lector', 'lector') ?>>Lector</option>
                <option value="admin"<?= selected($account['role'] ?? '', 'admin') ?>>Admin</option>
            </select>
        </label>
        <label>Estado
            <select name="is_active">
                <option value="1"<?= selected((string) ($account['is_active'] ?? '1'), '1') ?>>Activo</option>
                <option value="0"<?= selected((string) ($account['is_active'] ?? '1'), '0') ?>>Inactivo</option>
            </select>
        </label>
        <label><?= $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?>
            <input type="password" name="password" <?= $isEdit ? '' : 'required minlength="6"' ?> autocomplete="new-password">
        </label>
        <div class="full">
            <h2 style="margin:0 0 0.5rem;font-size:1.05rem">Libros que puede leer</h2>
            <p class="muted">Solo aplica al rol lector. El admin ve todos.</p>
            <div class="check-list">
                <?php if ($books === []): ?>
                    <p class="muted">Todavía no hay libros.</p>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <label>
                            <input type="checkbox" name="book_ids[]" value="<?= (int) $book['id'] ?>"<?= in_array((int) $book['id'], $assigned, true) ? ' checked' : '' ?>>
                            <?= e((string) $book['title']) ?>
                            <span class="muted">· <?= e((string) $book['author_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
