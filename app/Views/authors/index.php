<?php
/** @var list<array<string,mixed>> $rows */
$canWrite = $canWrite ?? false;
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Autores</h1>
            <p class="lede">Cada autor puede tener todos los libros que quieras.</p>
        </div>
        <?php if ($canWrite): ?>
            <a class="btn" href="<?= e(url('/autores/nuevo')) ?>">Nuevo autor</a>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($rows === []): ?>
        <div class="panel empty"><p>No hay autores todavía.</p></div>
    <?php else: ?>
        <div class="panel" style="padding:0;overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Autor</th>
                    <th>País</th>
                    <th>Libros</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/autores/' . (int) $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                        <td><?= e((string) ($row['country'] ?: '—')) ?></td>
                        <td><?= (int) ($row['book_count'] ?? 0) ?></td>
                        <td class="actions">
                            <?php if ($canWrite): ?>
                                <a class="btn ghost sm" href="<?= e(url('/autores/' . (int) $row['id'] . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
