<?php
/** @var list<array<string,mixed>> $authors */
/** @var list<array<string,mixed>> $books */
/** @var int $authorId */
/** @var int $bookId */
/** @var array<string,mixed>|null $selected */
/** @var array<string,mixed>|null $progress */
/** @var list<array<string,mixed>> $catalog */
/** @var array<string,mixed> $overview */
$chartPayload = [
    'overview' => $overview,
    'catalog' => array_map(static fn (array $row): array => [
        'title' => (string) $row['title'],
        'author' => (string) $row['author_name'],
        'status' => (string) ($row['status'] ?? ''),
        'genre' => (string) ($row['genre'] ?? ''),
        'pct' => (float) ($row['progress']['pct'] ?? 0),
        'pages' => (int) ($row['progress']['pages'] ?? 0),
        'chapters_done' => (int) ($row['progress']['chapters_done'] ?? 0),
        'chapters_total' => (int) ($row['progress']['chapters_total'] ?? 0),
        'has_outline' => (bool) ($row['progress']['has_outline'] ?? false),
        'has_synopsis' => (bool) ($row['progress']['has_synopsis'] ?? false),
    ], $catalog),
    'book' => $progress,
];
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p class="lede">Métricas del catálogo. Las páginas salen de sumar cada capítulo subido.</p>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="get" action="<?= e(url('/')) ?>" class="panel filters-bar">
        <input type="hidden" name="r" value="/">
        <label>Autor
            <select name="author_id" onchange="this.form.submit()">
                <option value="0">Todos</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?= (int) $author['id'] ?>"<?= selected($authorId, $author['id']) ?>><?= e((string) $author['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Libro
            <select name="book_id">
                <option value="0">Todos los del autor</option>
                <?php foreach ($books as $book): ?>
                    <option value="<?= (int) $book['id'] ?>"<?= selected($bookId, $book['id']) ?>>
                        <?= e((string) $book['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn">Ver</button>
    </form>

    <div class="stat-row">
        <div class="stat"><span class="stat-label">Libros</span><span class="stat-value"><?= (int) $overview['books'] ?></span></div>
        <div class="stat"><span class="stat-label">Páginas (borrador DOCX)</span><span class="stat-value"><?= e(format_n($overview['pages'])) ?></span></div>
        <div class="stat"><span class="stat-label">Avance medio</span><span class="stat-value"><?= e(format_pct($overview['avg_pct'])) ?></span></div>
        <div class="stat"><span class="stat-label">Capítulos cargados</span><span class="stat-value"><?= (int) $overview['chapters_done'] ?>/<?= (int) $overview['chapters_total'] ?></span></div>
    </div>
    <div class="stat-row">
        <div class="stat"><span class="stat-label">Autores</span><span class="stat-value"><?= (int) $overview['authors'] ?></span></div>
        <div class="stat"><span class="stat-label">Con outline</span><span class="stat-value"><?= (int) $overview['outline'] ?></span></div>
        <div class="stat"><span class="stat-label">Con sinopsis</span><span class="stat-value"><?= (int) $overview['synopsis'] ?></span></div>
        <div class="stat"><span class="stat-label">Libros al 100%</span><span class="stat-value"><?= (int) $overview['complete'] ?></span></div>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">Avance por libro</h2>
        <?php if ($catalog === []): ?>
            <p class="muted">Todavía no hay libros visibles.</p>
        <?php else: ?>
            <?php foreach ($catalog as $row): $p = $row['progress']; ?>
                <div class="progress-block">
                    <div class="progress-title"><?= e((string) $row['title']) ?></div>
                    <div class="progress-facts">
                        <?= e((string) $row['author_name']) ?>
                        · <?= e(format_pct($p['pct'])) ?> completo
                        · <?= e(format_n($p['pages'])) ?> pág.
                        · <?= e(format_n((int) ($p['words'] ?? 0))) ?> pal.
                        · <?= (int) $p['chapters_done'] ?>/<?= (int) $p['chapters_total'] ?> cap.
                        <?php if (!empty($p['last_upload_at'])): ?>
                            · Último capítulo <?= e(format_datetime((string) $p['last_upload_at'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php require __DIR__ . '/../partials/progress_bars.php'; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="charts-grid charts-grid-3">
        <div class="chart-card"><h2>Avance por libro</h2><div class="chart-wrap"><canvas data-chart="catalogPct"></canvas></div></div>
        <div class="chart-card"><h2>Páginas por libro</h2><div class="chart-wrap"><canvas data-chart="catalogPages"></canvas></div></div>
        <div class="chart-card"><h2>Libros por autor</h2><div class="chart-wrap"><canvas data-chart="byAuthor"></canvas></div></div>
        <div class="chart-card"><h2>Por estado</h2><div class="chart-wrap"><canvas data-chart="byStatus"></canvas></div></div>
        <div class="chart-card"><h2>Por género</h2><div class="chart-wrap"><canvas data-chart="byGenre"></canvas></div></div>
        <div class="chart-card"><h2>Distribución de avance</h2><div class="chart-wrap"><canvas data-chart="pctBuckets"></canvas></div></div>
        <div class="chart-card"><h2>Outline vs sinopsis</h2><div class="chart-wrap"><canvas data-chart="milestonesCatalog"></canvas></div></div>
        <div class="chart-card"><h2>Capítulos cargados vs planeados</h2><div class="chart-wrap"><canvas data-chart="chaptersCatalog"></canvas></div></div>
        <div class="chart-card"><h2>Completos vs en curso</h2><div class="chart-wrap"><canvas data-chart="completeSplit"></canvas></div></div>
    </div>

    <?php if ($selected && $progress): ?>
        <div class="page-head" style="margin-top:0.5rem">
            <div>
                <h2 style="margin:0"><?= e((string) $selected['title']) ?></h2>
                <p class="lede">Detalle del libro elegido · páginas = suma de capítulos</p>
            </div>
            <a class="btn ghost" href="<?= e(url('/libros/' . (int) $selected['id'])) ?>">Abrir ficha</a>
        </div>
        <div class="stat-row">
            <div class="stat"><span class="stat-label">Completo</span><span class="stat-value"><?= e(format_pct($progress['pct'])) ?></span></div>
            <div class="stat"><span class="stat-label">Páginas</span><span class="stat-value"><?= e(format_n($progress['pages'])) ?></span></div>
            <div class="stat"><span class="stat-label">Capítulos</span><span class="stat-value"><?= (int) $progress['chapters_done'] ?>/<?= (int) $progress['chapters_total'] ?></span></div>
            <div class="stat"><span class="stat-label">Promedio pág./cap.</span><span class="stat-value"><?= e(format_n($progress['avg_pages'], 1)) ?></span></div>
        </div>
        <div class="panel">
            <p class="muted" style="margin-top:0">Composición 100% por páginas de cada capítulo</p>
            <div class="progress-track">
                <?php
                $hasPages = (int) $progress['pages'] > 0;
                $shown = false;
                foreach ($progress['segments'] as $seg):
                    $width = $hasPages ? (float) $seg['pct'] : ((count($progress['segments']) > 0) ? (100 / count($progress['segments'])) : 0);
                    if ($width <= 0) {
                        continue;
                    }
                    $shown = true;
                    $bg = !empty($seg['done']) ? $seg['color'] : 'color-mix(in srgb, ' . $seg['color'] . ' 35%, var(--line))';
                    ?>
                    <div class="progress-seg" style="flex: <?= e((string) $width) ?> 1 0; background: <?= e($bg) ?>;" title="<?= e($seg['label'] . ' · ' . $seg['pages'] . ' pág.') ?>">
                        <?= e($seg['pages'] > 0 ? (string) $seg['pages'] : '') ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$shown): ?><div class="progress-empty"></div><?php endif; ?>
            </div>
        </div>
        <?php $prefix = 'book'; require __DIR__ . '/../partials/book_charts.php'; ?>
    <?php endif; ?>
</section>
<script type="application/json" id="booky-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
