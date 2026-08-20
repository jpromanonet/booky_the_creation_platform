<?php
/** @var array<string,mixed> $p */
/** @var bool $showHitos */
$showHitos = $showHitos ?? true;
$segments = is_array($p['segments'] ?? null) ? $p['segments'] : [];
$hasPages = (int) ($p['pages'] ?? 0) > 0;
?>
<?php if ($showHitos): ?>
    <div class="progress-track" aria-label="Porcentaje de completo">
        <div class="progress-seg" style="flex: <?= e((string) $p['pct']) ?> 1 0; background: <?= e($p['color']) ?>;"><?= e(format_pct($p['pct'], 0)) ?></div>
        <?php if ((float) $p['pct'] < 100): ?>
            <div class="progress-empty" style="flex: <?= e((string) max(0, 100 - (float) $p['pct'])) ?> 1 0;"></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($segments !== []): ?>
    <p class="progress-caption muted">Composición del manuscrito (100% = páginas sumadas)</p>
    <div class="progress-track progress-track-compose" aria-label="Composición del manuscrito">
        <?php
        $shown = false;
        foreach ($segments as $seg):
            $width = $hasPages ? (float) $seg['pct'] : (100 / max(1, count($segments)));
            if ($width <= 0) {
                continue;
            }
            $shown = true;
            $bg = !empty($seg['done']) ? $seg['color'] : 'color-mix(in srgb, ' . $seg['color'] . ' 35%, var(--line))';
            ?>
            <div class="progress-seg" style="flex: <?= e((string) $width) ?> 1 0; background: <?= e($bg) ?>;" title="<?= e($seg['label'] . ' · ' . format_n($seg['pages']) . ' pág. · ' . format_pct($seg['pct'])) ?>"></div>
        <?php endforeach; ?>
        <?php if (!$shown): ?>
            <div class="progress-empty"></div>
        <?php endif; ?>
    </div>
    <div class="progress-legend">
        <?php foreach ($segments as $seg): ?>
            <div class="legend-item">
                <i class="legend-dot" style="background: <?= e($seg['color']) ?>"></i>
                <span class="legend-name"><?= e($seg['label']) ?></span>
                <span class="legend-pct"><?= e(format_pct($seg['pct'])) ?></span>
                <span class="legend-pages"><?= e(format_n($seg['pages'])) ?> pág.</span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
