<?php
/** @var array<string,mixed>|null $me */
?>
<section class="page">
    <div class="page-head">
        <div>
            <h1>Configuración</h1>
            <p class="lede">Tu usuario de Booky.</p>
        </div>
    </div>
    <?php require __DIR__ . '/../partials/alerts.php'; ?>

    <div class="grid-2">
        <div class="panel">
            <h2>Datos de cuenta</h2>
            <form method="post" action="<?= e(url('/configuracion/perfil')) ?>" class="form-grid" style="padding:0">
                <?= csrf_field() ?>
                <label>Nombre *
                    <input name="name" required value="<?= e((string) ($me['name'] ?? '')) ?>">
                </label>
                <label>Email *
                    <input type="email" name="email" required value="<?= e((string) ($me['email'] ?? '')) ?>">
                </label>
                <div class="span-2"><button type="submit" class="btn">Guardar perfil</button></div>
            </form>
        </div>
        <div class="panel">
            <h2>Cambiar contraseña</h2>
            <form method="post" action="<?= e(url('/configuracion/password')) ?>" class="form-grid" style="padding:0">
                <?= csrf_field() ?>
                <label class="span-2">Contraseña actual *
                    <input type="password" name="current_password" required autocomplete="current-password">
                </label>
                <label>Nueva *
                    <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                </label>
                <label>Confirmar *
                    <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                </label>
                <div class="span-2"><button type="submit" class="btn">Actualizar contraseña</button></div>
            </form>
        </div>
    </div>
</section>
