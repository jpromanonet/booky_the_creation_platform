<?php
/** @var array<string,mixed>|null $author */
$isEdit = !empty($author['id']);
$action = $isEdit ? '/autores/' . (int) $author['id'] : '/autores';
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1><?= e($title) ?></h1>
            <p class="lede">Ficha del autor.</p>
        </div>
        <a class="btn ghost" href="<?= e(url($isEdit ? '/autores/' . (int) $author['id'] : '/autores')) ?>">Volver</a>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <form method="post" action="<?= e(url($action)) ?>" class="panel form-grid">
        <?= csrf_field() ?>
        <label>Nombre *
            <input name="name" required value="<?= e((string) ($author['name'] ?? '')) ?>">
        </label>
        <label>País
            <input name="country" value="<?= e((string) ($author['country'] ?? '')) ?>">
        </label>
        <label class="full">Bio
            <textarea name="bio" rows="4"><?= e((string) ($author['bio'] ?? '')) ?></textarea>
        </label>
        <div class="full"><button type="submit">Guardar</button></div>
    </form>
</section>
