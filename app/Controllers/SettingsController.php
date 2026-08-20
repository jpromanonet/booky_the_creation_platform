<?php

declare(strict_types=1);

final class SettingsController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $me = Auth::user();
        view('settings/index', [
            'title' => 'Configuración',
            'me' => $me ? UserService::find((int) $me['id']) : null,
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function updateProfile(): void
    {
        Auth::requireLogin();
        require_csrf();
        $me = Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $res = UserService::updateProfile((int) $me['id'], post_string('name'), post_string('email'));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Perfil actualizado.' : ($res['error'] ?? 'No se pudo guardar.'));
        redirect('/configuracion');
    }

    public static function changePassword(): void
    {
        Auth::requireLogin();
        require_csrf();
        $me = Auth::user();
        if (!$me) {
            redirect('/login');
        }
        $res = UserService::changePassword(
            (int) $me['id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Contraseña actualizada.' : ($res['error'] ?? 'No se pudo cambiar.'));
        redirect('/configuracion');
    }
}
