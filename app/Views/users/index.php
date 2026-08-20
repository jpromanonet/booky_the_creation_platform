<?php
/** @var list<array<string,mixed>> $rows */
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Usuarios</h1>
            <p class="lede">Admin ve todo. Lector solo los libros que le asignes.</p>
        </div>
        <a class="btn" href="<?= e(url('/usuarios/nuevo')) ?>">Nuevo usuario</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="panel" style="padding:0;overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['name']) ?></td>
                    <td><?= e((string) $row['email']) ?></td>
                    <td><?= e(role_label((string) $row['role'])) ?></td>
                    <td><?= (int) $row['is_active'] ? 'Activo' : 'Inactivo' ?></td>
                    <td><a class="btn ghost sm" href="<?= e(url('/usuarios/' . (int) $row['id'] . '/editar')) ?>">Editar</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
