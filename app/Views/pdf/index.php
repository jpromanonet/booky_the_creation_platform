<?php
/** @var list<array<string,mixed>> $rows */
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>PDF del manuscrito</h1>
            <p class="lede">Empalma los PDF de cierre con portada, índice y hojas de parte, y sella encabezado y pie (título, sección, autor y número de página) en todo el libro.</p>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($rows === []): ?>
        <div class="panel empty"><p>No hay libros para exportar.</p></div>
    <?php else: ?>
        <div class="book-list">
            <?php foreach ($rows as $row): ?>
                <?php
                $id = (int) $row['id'];
                $pdf = $row['pdf'] ?? ['ready' => 0, 'total' => 0, 'missing' => []];
                $ready = (int) $pdf['ready'];
                $total = (int) $pdf['total'];
                ?>
                <article class="panel book-list-card">
                    <div class="book-list-head">
                        <div>
                            <h2><?= e((string) $row['title']) ?></h2>
                            <p class="muted" style="margin:0.25rem 0 0">
                                <?= e((string) $row['author_name']) ?>
                                · <?= $ready ?>/<?= $total ?> secciones con PDF de cierre
                            </p>
                        </div>
                        <div class="actions">
                            <a class="btn" href="<?= e(url('/pdf/' . $id . '/descargar')) ?>">Generar PDF</a>
                            <a class="btn ghost" href="<?= e(url('/libros/' . $id)) ?>">Ver ficha</a>
                        </div>
                    </div>
                    <?php if ($total === 0): ?>
                        <p class="muted" style="margin:0.75rem 0 0">Definí la estructura en la ficha para poder armar el PDF.</p>
                    <?php elseif ($ready < $total): ?>
                        <p class="muted" style="margin:0.75rem 0 0">
                            Faltan PDF de cierre en <?= e(implode(', ', array_slice($pdf['missing'], 0, 8))) ?><?= count($pdf['missing']) > 8 ? '…' : '' ?>.
                            El libro se arma solo con los PDF que ya estén cargados.
                        </p>
                    <?php else: ?>
                        <p class="muted" style="margin:0.75rem 0 0">Todas las secciones tienen PDF de cierre. Se empalman con portada, índice y hojas de parte.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
