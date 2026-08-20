<?php
/** @var string $prefix */
$prefix = $prefix ?? 'g';
?>
<div class="charts-grid charts-grid-3">
    <div class="chart-card">
        <h2>Composición por capítulo</h2>
        <p class="chart-hint">% sobre el total de páginas sumadas</p>
        <div class="chart-wrap"><canvas data-chart="composition" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Páginas por capítulo</h2>
        <p class="chart-hint">Conteo automático del documento</p>
        <div class="chart-wrap"><canvas data-chart="pages" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Páginas acumuladas</h2>
        <p class="chart-hint">Suma capítulo a capítulo</p>
        <div class="chart-wrap"><canvas data-chart="cumulative" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Hitos</h2>
        <p class="chart-hint">Outline, sinopsis y capítulos</p>
        <div class="chart-wrap"><canvas data-chart="milestones" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Capítulos cargados</h2>
        <p class="chart-hint">Con documento vs pendientes</p>
        <div class="chart-wrap"><canvas data-chart="donePending" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Avance del libro</h2>
        <p class="chart-hint">Hitos cumplidos sobre el 100%</p>
        <div class="chart-wrap"><canvas data-chart="gauge" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Radar de páginas</h2>
        <p class="chart-hint">Peso relativo de cada capítulo</p>
        <div class="chart-wrap"><canvas data-chart="radar" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Polar de composición</h2>
        <p class="chart-hint">Misma data, otra lectura</p>
        <div class="chart-wrap"><canvas data-chart="polar" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
    <div class="chart-card">
        <h2>Promedio vs cada capítulo</h2>
        <p class="chart-hint">Línea de promedio de páginas</p>
        <div class="chart-wrap"><canvas data-chart="avgLine" data-scope="<?= e($prefix) ?>"></canvas></div>
    </div>
</div>
