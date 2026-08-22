<?php
/** @var array<string,mixed> $book */
/** @var list<array<string,mixed>> $chapters */
/** @var array<string,mixed>|null $outline */
/** @var array<string,mixed>|null $synopsis */
/** @var array<string,mixed> $progress */
$canWrite = $canWrite ?? false;
$bookPath = '/libros/' . (int) $book['id'];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e((string) $book['title']) ?></h1>
            <p class="lede"><?= e((string) $book['author_name']) ?><?= !empty($book['subtitle']) ? ' · ' . e((string) $book['subtitle']) : '' ?></p>
        </div>
        <div class="actions">
            <a class="btn" href="#capitulos">Cargar manuscrito</a>
            <a class="btn ghost" href="<?= e(url('/pdf/' . (int) $book['id'] . '/descargar')) ?>">Generar PDF</a>
            <?php if ($canWrite): ?>
                <a class="btn ghost" href="<?= e(url($bookPath . '/editar')) ?>">Editar ficha</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e(url('/libros')) ?>">Volver</a>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="stat-row cols-5">
        <div class="stat">
            <span class="stat-label">Completo</span>
            <span class="stat-value"><?= e(format_pct($progress['pct'])) ?></span>
            <span class="stat-hint"><?= (int) $progress['done'] ?>/<?= (int) $progress['total'] ?> hitos</span>
        </div>
        <div class="stat">
            <span class="stat-label">Páginas (borrador)</span>
            <span class="stat-value"><?= e(format_n($progress['pages'])) ?></span>
            <span class="stat-hint">Solo DOCX/ODT/ODS · el PDF no suma</span>
        </div>
        <div class="stat">
            <span class="stat-label">Palabras</span>
            <span class="stat-value"><?= e(format_n((int) ($progress['words'] ?? 0))) ?></span>
            <span class="stat-hint">Total del manuscrito en borrador</span>
        </div>
        <div class="stat">
            <span class="stat-label">Manuscrito</span>
            <span class="stat-value"><?= (int) $progress['chapters_done'] ?>/<?= (int) $progress['chapters_total'] ?></span>
            <span class="stat-hint"><?= (int) $progress['chapters_pending'] ?> pendientes</span>
        </div>
        <div class="stat">
            <span class="stat-label">Extremos</span>
            <span class="stat-value"><?= e(format_n($progress['min_pages'])) ?>–<?= e(format_n($progress['max_pages'])) ?></span>
            <span class="stat-hint">mín / máx páginas por capítulo</span>
        </div>
    </div>

    <?php $p = $progress; require __DIR__ . '/../partials/progress_bars.php'; ?>
    <div class="milestone-row">
        <span class="milestone <?= $progress['has_outline'] ? 'is-done' : 'is-pending' ?>">Outline</span>
        <span class="milestone <?= $progress['has_synopsis'] ? 'is-done' : 'is-pending' ?>">Sinopsis</span>
        <span class="milestone <?= $progress['chapters_done'] === $progress['chapters_total'] && $progress['chapters_total'] > 0 ? 'is-done' : 'is-pending' ?>">Manuscrito</span>
    </div>

    <?php
    $celebrateRaw = $celebrate ?? null;
    $celebrateData = null;
    if (is_string($celebrateRaw) && $celebrateRaw !== '') {
        $decoded = json_decode($celebrateRaw, true);
        if (is_array($decoded)) {
            $celebrateData = $decoded;
        }
    }
    ?>
    <?php if ($celebrateData): ?>
        <div class="celebrate-modal" id="celebrate-modal" role="dialog" aria-modal="true" aria-labelledby="celebrate-title">
            <div class="celebrate-backdrop" data-celebrate-close></div>
            <div class="celebrate-card">
                <p class="celebrate-kicker">Manuscrito completo</p>
                <h2 id="celebrate-title">¡Felicitaciones<?= !empty($celebrateData['author']) ? ', ' . e((string) $celebrateData['author']) : '' ?>!</h2>
                <p class="celebrate-body">
                    <strong><?= e((string) ($celebrateData['title'] ?? $book['title'] ?? 'El libro')) ?></strong>
                    ya tiene outline, sinopsis y todos los borradores cargados.
                    <?php if ((int) ($celebrateData['pages'] ?? 0) > 0 || (int) ($celebrateData['words'] ?? 0) > 0): ?>
                        Van <?= e(format_n((int) ($celebrateData['pages'] ?? 0))) ?> páginas
                        y <?= e(format_n((int) ($celebrateData['words'] ?? 0))) ?> palabras.
                    <?php endif; ?>
                </p>
                <p class="celebrate-note muted">Cuando quieras, los PDF de cierre arman el libro final.</p>
                <button type="button" class="btn" data-celebrate-close>Seguir escribiendo</button>
            </div>
        </div>
    <?php endif; ?>

    <script type="application/json" id="booky-charts"><?= json_encode(['book' => $progress, 'catalog' => [], 'overview' => []], JSON_UNESCAPED_UNICODE) ?></script>
    <?php $prefix = 'book'; require __DIR__ . '/../partials/book_charts.php'; ?>

    <?php if (!empty($book['description'])): ?>
        <div class="panel"><p style="margin:0"><?= nl2br(e((string) $book['description'])) ?></p></div>
    <?php endif; ?>

    <div class="doc-zones">
        <div class="panel doc-zone" id="outline">
            <h2>Outline</h2>
            <?php if ($outline): ?>
                <p>
                    <a href="<?= e(url($bookPath . '/archivos/' . (int) $outline['id'])) ?>"><?= e((string) $outline['original_name']) ?></a>
                    <span class="muted"> · <?= (int) $outline['page_count'] ?> pág.</span>
                </p>
                <p class="chapter-stamp">Última actualización: <strong><?= e(format_datetime((string) ($outline['created_at'] ?? ''))) ?></strong></p>
                <?php if ($canWrite): ?>
                    <form method="post" action="<?= e(url($bookPath . '/documentos/' . (int) $outline['id'] . '/eliminar')) ?>" class="inline"
                          onsubmit="return confirm('¿Quitar el outline?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn ghost danger sm">Quitar</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">Todavía no hay outline. Es un hito obligatorio.</p>
            <?php endif; ?>
            <?php if ($canWrite): ?>
                <?php $action = $bookPath . '/outline'; $hint = 'Outline · un archivo ODS o DOCX'; require __DIR__ . '/../partials/dropzone.php'; ?>
            <?php endif; ?>
        </div>

        <div class="panel doc-zone" id="sinopsis">
            <h2>Sinopsis</h2>
            <?php if ($synopsis): ?>
                <p>
                    <a href="<?= e(url($bookPath . '/archivos/' . (int) $synopsis['id'])) ?>"><?= e((string) $synopsis['original_name']) ?></a>
                    <span class="muted"> · <?= (int) $synopsis['page_count'] ?> pág.</span>
                </p>
                <p class="chapter-stamp">Última actualización: <strong><?= e(format_datetime((string) ($synopsis['created_at'] ?? ''))) ?></strong></p>
                <?php if ($canWrite): ?>
                    <form method="post" action="<?= e(url($bookPath . '/documentos/' . (int) $synopsis['id'] . '/eliminar')) ?>" class="inline"
                          onsubmit="return confirm('¿Quitar la sinopsis?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn ghost danger sm">Quitar</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">Todavía no hay sinopsis. Es un hito obligatorio.</p>
            <?php endif; ?>
            <?php if ($canWrite): ?>
                <?php $action = $bookPath . '/sinopsis'; $hint = 'Sinopsis · un archivo ODS o DOCX'; require __DIR__ . '/../partials/dropzone.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel" id="capitulos">
        <h2 style="margin-top:0">Manuscrito</h2>
        <p class="muted">Cada sección tiene dos espacios: el borrador (DOCX/ODT/ODS) es el que Booky cuenta. El PDF de cierre no suma páginas: solo se usa al exportar el libro.</p>
        <?php
        $sections = $sections ?? [];
        $chapterNum = 0;
        ?>
        <?php if ($sections === []): ?>
            <p class="muted">Definí la estructura en Editar ficha.</p>
        <?php else: ?>
            <?php foreach ($sections as $group): ?>
                <section class="manuscript-group">
                    <h3 class="manuscript-heading"><?= e((string) $group['heading']) ?></h3>
                    <div class="chapter-drops">
                        <?php foreach ($group['items'] as $ch):
                            $kind = (string) ($ch['kind'] ?? 'chapter');
                            if ($kind === 'chapter') {
                                $chapterNum++;
                                $kicker = 'Capítulo ' . $chapterNum;
                            } else {
                                $kicker = section_kind_label($kind);
                            }
                            ?>
                            <article class="chapter-drop" id="capitulo-<?= (int) $ch['id'] ?>">
                                <header>
                                    <span class="muted"><?= e($kicker) ?></span>
                                    <h3><?= e((string) $ch['title']) ?></h3>
                                </header>
                                <div class="file-slots">
                                    <div class="file-slot">
                                        <h4>Borrador</h4>
                                        <p class="muted slot-help">DOCX, ODT u ODS. Booky cuenta las páginas de este archivo.</p>
                                        <?php if (!empty($ch['document_id'])): ?>
                                            <p>
                                                <a href="<?= e(url($bookPath . '/archivos/' . (int) $ch['document_id'])) ?>"><?= e((string) $ch['original_name']) ?></a>
                                                <span class="muted"> · <?= e(format_n((int) ($ch['page_count'] ?? 0))) ?> pág.<?php
                                                    $w = (int) ($ch['word_count'] ?? 0);
                                                    if ($w <= 0 && !empty($ch['document_id'])) {
                                                        foreach ($progress['segments'] ?? [] as $seg) {
                                                            if ((int) ($seg['chapter_id'] ?? 0) === (int) $ch['id']) {
                                                                $w = (int) ($seg['words'] ?? 0);
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    if ($w > 0):
                                                        ?> · <?= e(format_n($w)) ?> pal.<?php endif; ?></span>
                                            </p>
                                            <p class="chapter-stamp">Última actualización: <strong><?= e(format_datetime((string) $ch['uploaded_at'])) ?></strong></p>
                                            <?php if ($canWrite): ?>
                                                <form method="post" action="<?= e(url($bookPath . '/documentos/' . (int) $ch['document_id'] . '/eliminar')) ?>" class="inline"
                                                      onsubmit="return confirm('¿Quitar el borrador?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn ghost danger sm">Quitar</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p><span class="badge badge-warn">Sin borrador</span></p>
                                        <?php endif; ?>
                                        <?php if ($canWrite): ?>
                                            <?php
                                            $action = $bookPath . '/capitulos/' . (int) $ch['id'];
                                            $hint = 'Borrador · ODS, ODT o DOCX';
                                            $accept = '.ods,.odt,.docx';
                                            require __DIR__ . '/../partials/dropzone.php';
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-slot file-slot-pdf">
                                        <h4>PDF de cierre</h4>
                                        <p class="muted slot-help">Cuando esté seguro, subí el PDF. No cuenta para el avance: solo se empalma al exportar.</p>
                                        <?php if (!empty($ch['pdf_id'])): ?>
                                            <p>
                                                <a href="<?= e(url($bookPath . '/archivos/' . (int) $ch['pdf_id'])) ?>"><?= e((string) $ch['pdf_name']) ?></a>
                                            </p>
                                            <p class="chapter-stamp">Última actualización: <strong><?= e(format_datetime((string) $ch['pdf_uploaded_at'])) ?></strong></p>
                                            <?php if ($canWrite): ?>
                                                <form method="post" action="<?= e(url($bookPath . '/documentos/' . (int) $ch['pdf_id'] . '/eliminar')) ?>" class="inline"
                                                      onsubmit="return confirm('¿Quitar el PDF de cierre?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn ghost danger sm">Quitar</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p><span class="badge badge-muted">Sin PDF</span></p>
                                        <?php endif; ?>
                                        <?php if ($canWrite): ?>
                                            <?php
                                            $action = $bookPath . '/capitulos/' . (int) $ch['id'] . '/pdf';
                                            $hint = 'PDF de cierre · un solo archivo';
                                            $accept = '.pdf';
                                            require __DIR__ . '/../partials/dropzone.php';
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($canWrite): ?>
            <p class="muted">Para agregar partes, introducción, epílogo o capítulos usá Editar ficha.</p>
        <?php endif; ?>
    </div>

    <?php if ($canWrite): ?>
        <div class="panel" style="border-color: rgba(240, 113, 120, 0.35)">
            <h2 style="margin:0;color:var(--danger)">Eliminar libro</h2>
            <p class="muted">Borra el libro, capítulos y archivos.</p>
            <form method="post" action="<?= e(url($bookPath . '/eliminar')) ?>" onsubmit="return confirm('¿Eliminar este libro y sus archivos?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn danger">Eliminar</button>
            </form>
        </div>
    <?php endif; ?>
</section>
