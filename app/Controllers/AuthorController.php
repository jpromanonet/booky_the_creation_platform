<?php

declare(strict_types=1);

final class AuthorController
{
    public static function index(): void
    {
        Auth::requireLogin();
        view('authors/index', [
            'title' => 'Autores',
            'rows' => AuthorService::all(),
            'canWrite' => Auth::isAdmin(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdmin();
        view('authors/form', [
            'title' => 'Nuevo autor',
            'author' => null,
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = AuthorService::create(post_string('name'), post_string('bio'), post_string('country'));
        if (!$res['ok']) {
            flash('error', $res['error'] ?? 'No se pudo crear.');
            redirect('/autores/nuevo');
        }
        flash('success', 'Autor creado.');
        redirect('/autores/' . (int) $res['id']);
    }

    public static function show(string $id): void
    {
        Auth::requireLogin();
        $author = AuthorService::find((int) $id);
        if (!$author) {
            http_response_code(404);
            echo 'Autor no encontrado';
            return;
        }
        view('authors/show', [
            'title' => (string) $author['name'],
            'author' => $author,
            'books' => BookService::all((int) $author['id']),
            'canWrite' => Auth::isAdmin(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function edit(string $id): void
    {
        Auth::requireAdmin();
        $author = AuthorService::find((int) $id);
        if (!$author) {
            http_response_code(404);
            echo 'Autor no encontrado';
            return;
        }
        view('authors/form', [
            'title' => 'Editar autor',
            'author' => $author,
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = AuthorService::update((int) $id, post_string('name'), post_string('bio'), post_string('country'));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Autor actualizado.' : ($res['error'] ?? 'No se pudo guardar.'));
        redirect('/autores/' . (int) $id);
    }

    public static function destroy(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        AuthorService::delete((int) $id);
        flash('success', 'Autor eliminado.');
        redirect('/autores');
    }
}
