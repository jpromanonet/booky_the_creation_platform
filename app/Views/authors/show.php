<?php
/** @var array<string,mixed> $author */
/** @var list<array<string,mixed>> $books */
$canWrite = $canWrite ?? false;
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e((string) $author['name']) ?></h1>
            <p class="lede"><?= e((string) ($author['country'] ?: 'Autor')) ?></p>
        </div>
        <div class="actions">
            <?php if ($canWrite): ?>
                <a class="btn" href="<?= e(url('/libros/nuevo?author_id=' . (int) $author['id'])) ?>">Nuevo libro</a>
                <a class="btn ghost" href="<?= e(url('/autores/' . (int) $author['id'] . '/editar')) ?>">Editar</a>
                <form method="post" action="<?= e(url('/autores/' . (int) $author['id'] . '/eliminar')) ?>" class="inline"
                      onsubmit="return confirm('¿Eliminar este autor y todos sus libros?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn danger">Eliminar</button>
                </form>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url('/autores')) ?>">Volver</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if (!empty($author['bio'])): ?>
        <div class="panel"><p style="margin:0"><?= nl2br(e((string) $author['bio'])) ?></p></div>
    <?php endif; ?>

    <div class="panel" style="padding:0;overflow:auto">
        <table class="table">
            <thead><tr><th>Libro</th><th>Género</th><th>Estado</th></tr></thead>
            <tbody>
            <?php if ($books === []): ?>
                <tr><td colspan="3" class="muted">Sin libros.</td></tr>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td><a href="<?= e(url('/libros/' . (int) $book['id'])) ?>"><?= e((string) $book['title']) ?></a></td>
                        <td><?= e((string) ($book['genre'] ?: '—')) ?></td>
                        <td><?= e((string) ($book['status'] ?: '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
