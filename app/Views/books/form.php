<?php
/** @var array<string,mixed> $book */
/** @var array{intro:?array,epilogue:?array,parts:list,chapters:list} $structure */
/** @var list<array<string,mixed>> $authors */
$isEdit = !empty($book['id']);
$action = $isEdit ? '/libros/' . (int) $book['id'] : '/libros';
$intro = $structure['intro'] ?? null;
$epilogue = $structure['epilogue'] ?? null;
$parts = is_array($structure['parts'] ?? null) ? $structure['parts'] : [];
$chapters = is_array($structure['chapters'] ?? null) ? $structure['chapters'] : [['id' => '', 'title' => '']];
if ($chapters === []) {
    $chapters = [['id' => '', 'title' => '', 'part_id' => '']];
}
$chapterCount = max(1, count($chapters));

$partIndexOf = static function (array $ch, array $parts): int {
    $pid = (int) ($ch['part_id'] ?? 0);
    if ($pid <= 0) {
        return 0;
    }
    foreach ($parts as $i => $part) {
        if ((int) ($part['id'] ?? 0) === $pid) {
            return $i + 1;
        }
    }
    return 0;
};
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Definí partes y capítulos por separado. Cada capítulo elige a qué parte pertenece.</p>
        </div>
        <a class="btn ghost" href="<?= e(url($isEdit ? '/libros/' . (int) $book['id'] : '/libros')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <?php if ($authors === []): ?>
        <div class="alert alert-warn">Primero creá un autor.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url($action)) ?>" class="panel form-grid" id="book-structure-form">
        <?= csrf_field() ?>
        <label>Autor *
            <select name="author_id" required>
                <option value="">Elegí</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?= (int) $author['id'] ?>"<?= selected($book['author_id'] ?? '', $author['id']) ?>>
                        <?= e((string) $author['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Título *
            <input name="title" required value="<?= e((string) ($book['title'] ?? '')) ?>">
        </label>
        <label>Subtítulo
            <input name="subtitle" value="<?= e((string) ($book['subtitle'] ?? '')) ?>">
        </label>
        <label>Género
            <input name="genre" value="<?= e((string) ($book['genre'] ?? '')) ?>">
        </label>
        <label>Idioma
            <input name="language" value="<?= e((string) ($book['language'] ?? 'es')) ?>">
        </label>
        <label>ISBN
            <input name="isbn" value="<?= e((string) ($book['isbn'] ?? '')) ?>">
        </label>
        <label>Cantidad de capítulos *
            <input type="number" min="1" max="80" id="chapter-count" name="chapter_count" required value="<?= (int) $chapterCount ?>">
        </label>
        <label>Estado
            <select name="status">
                <?php foreach (['en_proceso' => 'En proceso', 'revision' => 'En revisión', 'completo' => 'Completo'] as $val => $lab): ?>
                    <option value="<?= e($val) ?>"<?= selected($book['status'] ?? 'en_proceso', $val) ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="full">Descripción
            <textarea name="description" rows="4"><?= e((string) ($book['description'] ?? '')) ?></textarea>
        </label>

        <div class="full structure-editor" id="structure-editor">
            <h2 style="margin:0 0 0.35rem;font-size:1.05rem">Estructura del manuscrito</h2>
            <p class="muted" style="margin:0 0 1rem">Introducción y epílogo son opcionales. Las partes son etiquetas: no anidan capítulos. En cada capítulo elegís la parte.</p>

            <div class="structure-block">
                <label class="inline-check">
                    <input type="checkbox" name="include_intro" value="1" id="include-intro"<?= $intro ? ' checked' : '' ?>>
                    Incluir introducción
                </label>
                <div class="special-fields" id="intro-fields"<?= $intro ? '' : ' hidden' ?>>
                    <input type="hidden" name="intro_id" value="<?= e((string) ($intro['id'] ?? '')) ?>">
                    <input name="intro_title" placeholder="Introducción" value="<?= e((string) ($intro['title'] ?? 'Introducción')) ?>">
                </div>
            </div>

            <div class="structure-block">
                <div class="structure-block-head">
                    <h3>Partes</h3>
                    <button type="button" class="btn ghost sm" id="add-part">Agregar parte</button>
                </div>
                <p class="muted" style="margin:0 0 0.65rem">Solo el nombre (Parte I, Parte II…). Después asignás cada capítulo desde la lista de abajo.</p>
                <div id="parts-editor">
                    <?php foreach ($parts as $pi => $part): ?>
                        <div class="part-row" data-part>
                            <input type="hidden" data-name="part-id" name="parts[<?= (int) $pi ?>][id]" value="<?= e((string) ($part['id'] ?? '')) ?>">
                            <input data-name="part-title" name="parts[<?= (int) $pi ?>][title]" placeholder="Parte <?= e(format_roman($pi + 1)) ?>" value="<?= e((string) ($part['title'] ?? '')) ?>">
                            <button type="button" class="btn ghost sm" data-remove-part>Quitar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="structure-block">
                <div class="structure-block-head">
                    <h3>Capítulos</h3>
                    <button type="button" class="btn ghost sm" id="add-chapter">Agregar capítulo</button>
                </div>
                <div class="chapter-editor" id="chapter-editor">
                    <?php foreach ($chapters as $i => $ch): ?>
                        <?php $sel = $partIndexOf($ch, $parts); ?>
                        <div class="chapter-row chapter-row-assign">
                            <input type="hidden" data-name="id" name="chapter_id[]" value="<?= e((string) ($ch['id'] ?? '')) ?>">
                            <input data-name="title" name="chapter_title[]" placeholder="Capítulo <?= $i + 1 ?>" value="<?= e((string) ($ch['title'] ?? '')) ?>">
                            <select data-part-select name="chapter_part[]" aria-label="Parte del capítulo"<?= $parts === [] ? ' disabled' : '' ?>>
                                <option value="">Sin parte</option>
                                <?php foreach ($parts as $pi => $part): ?>
                                    <option value="<?= $pi + 1 ?>"<?= selected($sel, $pi + 1) ?>>
                                        <?= e((string) (($part['title'] ?? '') !== '' ? $part['title'] : ('Parte ' . format_roman($pi + 1)))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn ghost sm" data-remove-chapter>Quitar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="structure-block">
                <label class="inline-check">
                    <input type="checkbox" name="include_epilogue" value="1" id="include-epilogue"<?= $epilogue ? ' checked' : '' ?>>
                    Incluir epílogo
                </label>
                <div class="special-fields" id="epilogue-fields"<?= $epilogue ? '' : ' hidden' ?>>
                    <input type="hidden" name="epilogue_id" value="<?= e((string) ($epilogue['id'] ?? '')) ?>">
                    <input name="epilogue_title" placeholder="Epílogo" value="<?= e((string) ($epilogue['title'] ?? 'Epílogo')) ?>">
                </div>
            </div>
        </div>
        <div class="full"><button type="submit">Guardar ficha</button></div>
    </form>
</section>
<template id="chapter-row-tpl">
    <div class="chapter-row chapter-row-assign">
        <input type="hidden" data-name="id" name="chapter_id[]" value="">
        <input data-name="title" name="chapter_title[]" placeholder="Capítulo">
        <select data-part-select name="chapter_part[]" aria-label="Parte del capítulo" disabled>
            <option value="">Sin parte</option>
        </select>
        <button type="button" class="btn ghost sm" data-remove-chapter>Quitar</button>
    </div>
</template>
<template id="part-row-tpl">
    <div class="part-row" data-part>
        <input type="hidden" data-name="part-id" value="">
        <input data-name="part-title" placeholder="Parte">
        <button type="button" class="btn ghost sm" data-remove-part>Quitar</button>
    </div>
</template>
