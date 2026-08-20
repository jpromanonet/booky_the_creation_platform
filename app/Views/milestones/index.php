<?php
/** @var array<string,string> $hero */
/** @var list<array<string,mixed>> $cards */
/** @var array<string,mixed> $overview */
/** @var list<array<string,mixed>> $authors */
/** @var int $authorId */
/** @var string $authorName */
$moodLabel = [
    'stalled' => 'Necesita un empujón',
    'starting' => 'Primer impulso',
    'moving' => 'En marcha',
    'almost' => 'Casi',
    'done' => 'Cerrado',
];
$overallPct = (float) ($overview['overall_pct'] ?? 0);
$overallColor = (string) ($overview['overall_color'] ?? '#4ade80');
$moods = ['stalled' => 0, 'starting' => 0, 'moving' => 0, 'almost' => 0, 'done' => 0];
foreach ($cards as $card) {
    $m = (string) ($card['mood'] ?? 'moving');
    if (isset($moods[$m])) {
        $moods[$m]++;
    }
}
$chartPayload = [
    'overview' => $overview,
    'moods' => $moods,
    'cards' => array_map(static fn (array $c): array => [
        'title' => (string) ($c['title'] ?? ''),
        'pct' => (float) ($c['pct'] ?? 0),
        'pages' => (int) ($c['pages'] ?? 0),
        'pending' => (int) ($c['pending'] ?? 0),
        'done' => (int) ($c['done'] ?? 0),
        'total' => (int) ($c['total'] ?? 0),
        'days_idle' => (int) ($c['days_idle'] ?? 0),
        'mood' => (string) ($c['mood'] ?? ''),
        'author' => (string) ($c['author'] ?? ''),
    ], $cards),
    'catalog' => array_map(static fn (array $c): array => [
        'title' => (string) ($c['title'] ?? ''),
        'pct' => (float) ($c['pct'] ?? 0),
        'pages' => (int) ($c['pages'] ?? 0),
        'chapters_done' => (int) ($c['done'] ?? 0),
        'chapters_total' => (int) ($c['total'] ?? 0),
        'pending' => (int) ($c['pending'] ?? 0),
        'days_idle' => min(60, (int) ($c['days_idle'] ?? 0)),
    ], $cards),
];
$scopeNote = $authorName !== '' ? ' · ' . $authorName : ' de todos los libros';
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Milestones</h1>
            <p class="lede">Qué falta, por qué se puede terminar, y el próximo paso — no toda la montaña.</p>
        </div>
    </div>

    <form method="get" action="<?= e(url('/milestones')) ?>" class="panel filters-bar">
        <input type="hidden" name="r" value="/milestones">
        <label>Autor
            <select name="author_id" onchange="this.form.submit()">
                <option value="0">Todos</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?= (int) $author['id'] ?>"<?= selected($authorId, $author['id']) ?>>
                        <?= e((string) $author['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <noscript><button type="submit" class="btn">Filtrar</button></noscript>
    </form>

    <div class="panel coach-hero">
        <p class="stat-label"><?= e($hero['kicker'] ?? '') ?></p>
        <h2><?= e($hero['title'] ?? '') ?></h2>
        <p class="lede" style="margin-top:0.65rem"><?= e($hero['text'] ?? '') ?></p>

        <p class="progress-caption" style="margin:1.15rem 0 0.45rem">
            Avance general<?= e($scopeNote) ?>
            <?php if ($overallPct >= 50): ?>
                · ya cruzaste el ecuador
            <?php else: ?>
                · el verde se oscurece al pasar el 50%
            <?php endif; ?>
        </p>
        <div class="progress-track progress-track-overall" aria-label="Avance general">
            <div class="progress-seg" style="flex: <?= e((string) $overallPct) ?> 1 0; background: <?= e($overallColor) ?>;">
                <?= e(format_pct($overallPct, 0)) ?>
            </div>
            <?php if ($overallPct < 100): ?>
                <div class="progress-empty" style="flex: <?= e((string) max(0, 100 - $overallPct)) ?> 1 0;"></div>
            <?php endif; ?>
        </div>
        <p class="muted" style="margin:0.85rem 0 0">
            <?= (int) ($overview['overall_done'] ?? 0) ?>/<?= (int) ($overview['overall_total'] ?? 0) ?> hitos
            · <?= (int) $overview['chapters_done'] ?>/<?= (int) $overview['chapters_total'] ?> capítulos
            · <?= e(format_n($overview['pages'])) ?> páginas
            · <?= (int) ($overview['over_half'] ?? 0) ?> de <?= (int) $overview['books'] ?> libros sobre el 50%
        </p>
    </div>

    <?php if ($cards !== []): ?>
        <div class="charts-grid charts-grid-3" style="margin-bottom:1.25rem">
            <div class="chart-card tone-green">
                <h2>El tablero ya escrito</h2>
                <p class="chart-hint">Hitos cumplidos vs lo que falta. Verde siempre.</p>
                <div class="chart-wrap"><canvas data-chart="overallGauge"></canvas></div>
            </div>
            <div class="chart-card tone-green">
                <h2>Quién ya pasó la mitad</h2>
                <p class="chart-hint">Libros sobre 50% vs los que todavía empujan.</p>
                <div class="chart-wrap"><canvas data-chart="halfSplit"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Clima de los libros</h2>
                <p class="chart-hint">Estancados, en marcha, casi y cerrados.</p>
                <div class="chart-wrap"><canvas data-chart="moodMix"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Carrera de avance</h2>
                <p class="chart-hint">Cada barra es un libro. Subir una ya cuenta.</p>
                <div class="chart-wrap"><canvas data-chart="catalogPct"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Páginas que ya existen</h2>
                <p class="chart-hint">No es lo que falta: es lo que ya está en el mundo.</p>
                <div class="chart-wrap"><canvas data-chart="catalogPages"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Capítulos ganados</h2>
                <p class="chart-hint">Cargados contra el plan de cada ficha.</p>
                <div class="chart-wrap"><canvas data-chart="chaptersCatalog"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Cobertura de hitos</h2>
                <p class="chart-hint">% de libros con outline, sinopsis y ritmo de capítulos.</p>
                <div class="chart-wrap"><canvas data-chart="hitCoverage"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Días sin archivo nuevo</h2>
                <p class="chart-hint">Un hueco no borra el trabajo. Sirve para elegir a quién volver.</p>
                <div class="chart-wrap"><canvas data-chart="idleDays"></canvas></div>
            </div>
            <div class="chart-card">
                <h2>Avance medio por autor</h2>
                <p class="chart-hint">Cómo viene cada firma en el recorte actual.</p>
                <div class="chart-wrap"><canvas data-chart="authorPct"></canvas></div>
            </div>
        </div>
        <script type="application/json" id="booky-charts"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <?php if ($cards === []): ?>
        <div class="panel empty">
            <p><?= $authorId > 0 ? 'Este autor todavía no tiene libros visibles.' : 'Cuando exista un libro, acá va a aparecer el pep talk con números reales.' ?></p>
            <?php if (Auth::isAdmin()): ?>
                <p class="empty-cta"><a class="btn" href="<?= e(url('/libros/nuevo')) ?>">Crear el primer libro</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="coach-list">
            <?php foreach ($cards as $card): ?>
                <article class="panel coach-card mood-<?= e((string) $card['mood']) ?>">
                    <div class="coach-card-head">
                        <span class="badge <?= $card['mood'] === 'stalled' ? 'badge-warn' : ($card['mood'] === 'done' ? 'badge-ok' : 'badge-muted') ?>">
                            <?= e($moodLabel[$card['mood']] ?? $card['mood']) ?>
                        </span>
                        <span class="muted"><?= e((string) $card['author']) ?></span>
                    </div>
                    <h2><?= e((string) $card['headline']) ?></h2>
                    <p><?= e((string) $card['body']) ?></p>
                    <div class="progress-track" style="margin:0.85rem 0 0.4rem">
                        <div class="progress-seg" style="flex: <?= e((string) $card['pct']) ?> 1 0; background: <?= e((string) $card['color']) ?>;"><?= e(format_pct($card['pct'], 0)) ?></div>
                        <?php if ((float) $card['pct'] < 100): ?>
                            <div class="progress-empty" style="flex: <?= e((string) max(0, 100 - (float) $card['pct'])) ?> 1 0;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="progress-meta muted">
                        <span><?= (int) $card['done'] ?>/<?= (int) $card['total'] ?> cap. · <?= e(format_n($card['pages'])) ?> pág.</span>
                        <span>
                            <?php if (!empty($card['last_upload_at'])): ?>
                                Último archivo <?= e(format_datetime((string) $card['last_upload_at'])) ?>
                            <?php else: ?>
                                Todavía no hay capítulos subidos
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="actions" style="margin-top:0.85rem">
                        <a class="btn" href="<?= e(url((string) $card['cta_href'])) ?>"><?= e((string) $card['cta_label']) ?></a>
                        <a class="btn ghost" href="<?= e(url('/libros/' . (int) $card['book_id'])) ?>">Ver ficha</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
