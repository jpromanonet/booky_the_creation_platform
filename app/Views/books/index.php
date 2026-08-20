<?php
/** @var list<array<string,mixed>> $rows */
$canWrite = $canWrite ?? false;
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Libros</h1>
            <p class="lede">Avance según outline, sinopsis y el manuscrito cargado.</p>
        </div>
        <?php if ($canWrite): ?>
            <a class="btn" href="<?= e(url('/libros/nuevo')) ?>">Nuevo libro</a>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($rows === []): ?>
        <div class="panel empty"><p>No hay libros.</p></div>
    <?php else: ?>
        <div class="book-list">
            <?php foreach ($rows as $row): $p = $row['progress']; $id = (int) $row['id']; ?>
                <article class="panel book-list-card">
                    <div class="book-list-head">
                        <div>
                            <h2><?= e((string) $row['title']) ?></h2>
                            <p class="muted" style="margin:0.25rem 0 0">
                                <?= e((string) $row['author_name']) ?>
                                · <?= e(format_n($p['pages'])) ?> pág.
                                · <?= (int) $p['chapters_done'] ?>/<?= (int) $p['chapters_total'] ?> sec.
                                <?php if (!empty($p['last_upload_at'])): ?>
                                    · Última carga: <?= e(format_datetime((string) $p['last_upload_at'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="actions">
                            <a class="btn" href="<?= e(url('/libros/' . $id . '#capitulos')) ?>">Cargar manuscrito</a>
                            <a class="btn ghost" href="<?= e(url('/libros/' . $id)) ?>">Ver ficha</a>
                            <?php if ($canWrite): ?>
                                <a class="btn ghost" href="<?= e(url('/libros/' . $id . '/editar')) ?>">Editar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress-track" style="margin-top:1rem">
                        <div class="progress-seg" style="flex: <?= e((string) $p['pct']) ?> 1 0; background: <?= e($p['color']) ?>;"><?= e(format_pct($p['pct'], 0)) ?></div>
                        <?php if ((float) $p['pct'] < 100): ?>
                            <div class="progress-empty" style="flex: <?= e((string) max(0, 100 - $p['pct'])) ?> 1 0;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="progress-meta muted">
                        <span><?= e(format_pct($p['pct'])) ?> completo</span>
                        <span>Outline <?= !empty($p['has_outline']) ? 'listo' : 'pendiente' ?> · Sinopsis <?= !empty($p['has_synopsis']) ? 'lista' : 'pendiente' ?></span>
                    </div>
                    <?php $showHitos = false; require __DIR__ . '/../partials/progress_bars.php'; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
