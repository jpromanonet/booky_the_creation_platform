<?php

declare(strict_types=1);

final class UserController
{
    public static function index(): void
    {
        Auth::requireAdmin();
        view('users/index', [
            'title' => 'Usuarios',
            'rows' => UserService::all(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdmin();
        view('users/form', [
            'title' => 'Nuevo usuario',
            'account' => null,
            'assigned' => [],
            'books' => BookService::all(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = UserService::create(
            post_string('name'),
            post_string('email'),
            (string) ($_POST['password'] ?? ''),
            post_string('role'),
            (string) ($_POST['is_active'] ?? '1') === '1'
        );
        if (!$res['ok']) {
            flash('error', $res['error'] ?? 'No se pudo crear.');
            redirect('/usuarios/nuevo');
        }
        UserService::syncBooks((int) $res['id'], self::postedBookIds());
        flash('success', 'Usuario creado.');
        redirect('/usuarios');
    }

    public static function edit(string $id): void
    {
        Auth::requireAdmin();
        $account = UserService::find((int) $id);
        if (!$account) {
            http_response_code(404);
            echo 'Usuario no encontrado';
            return;
        }
        view('users/form', [
            'title' => 'Editar usuario',
            'account' => $account,
            'assigned' => UserService::bookIds((int) $id),
            'books' => BookService::all(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = UserService::update(
            (int) $id,
            post_string('name'),
            post_string('email'),
            post_string('role'),
            (string) ($_POST['is_active'] ?? '1') === '1',
            (string) ($_POST['password'] ?? '')
        );
        if ($res['ok']) {
            UserService::syncBooks((int) $id, self::postedBookIds());
        }
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Usuario actualizado.' : ($res['error'] ?? 'No se pudo guardar.'));
        redirect('/usuarios');
    }

    /** @return list<int> */
    private static function postedBookIds(): array
    {
        $raw = $_POST['book_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        return array_map(static fn ($v): int => (int) $v, $raw);
    }
}
