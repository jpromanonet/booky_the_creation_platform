<?php
/** @var string $action */
/** @var string $hint */
/** @var string $accept */
$hint = $hint ?? 'Un ODS o DOCX · arrastrá uno solo o hacé clic';
$accept = $accept ?? '.ods,.odt,.docx';
$exts = strtolower(str_replace('.', '', $accept));
?>
<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" class="drop-form" data-ext="<?= e($exts) ?>">
    <?= csrf_field() ?>
    <label class="dropzone">
        <input class="dropzone-input" type="file" name="file" required accept="<?= e($accept) ?>">
        <strong class="dropzone-title">Arrastrá el archivo acá</strong>
        <span class="dropzone-hint"><?= e($hint) ?></span>
        <span class="dropzone-file" hidden></span>
    </label>
</form>
