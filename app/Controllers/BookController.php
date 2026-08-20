<?php

declare(strict_types=1);

final class BookController
{
    public static function index(): void
    {
        Auth::requireLogin();
        view('books/index', [
            'title' => 'Libros',
            'rows' => ProgressService::catalog(),
            'canWrite' => Auth::isAdmin(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function create(): void
    {
        Auth::requireAdmin();
        $authorId = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
        view('books/form', [
            'title' => 'Nuevo libro',
            'book' => ['author_id' => $authorId],
            'structure' => BookService::formStructure(null),
            'authors' => AuthorService::all(),
            'error' => flash('error'),
        ]);
    }

    public static function store(): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = BookService::create(self::payload(), self::structureFromPost());
        if (!$res['ok']) {
            flash('error', $res['error'] ?? 'No se pudo crear.');
            redirect('/libros/nuevo');
        }
        flash('success', 'Libro creado. Cargá outline, sinopsis y el manuscrito para medir el avance.');
        redirect('/libros/' . (int) $res['id']);
    }

    public static function show(string $id): void
    {
        $bookId = (int) $id;
        AccessService::requireBook($bookId);
        $book = BookService::find($bookId);
        if (!$book) {
            http_response_code(404);
            echo 'Libro no encontrado';
            return;
        }
        view('books/show', [
            'title' => (string) $book['title'],
            'book' => $book,
            'chapters' => BookService::chapters($bookId),
            'sections' => BookService::groupedSections($bookId),
            'outline' => DocumentService::milestone($bookId, DocumentService::KIND_OUTLINE),
            'synopsis' => DocumentService::milestone($bookId, DocumentService::KIND_SYNOPSIS),
            'progress' => ProgressService::forBook($bookId),
            'canWrite' => Auth::isAdmin(),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }

    public static function edit(string $id): void
    {
        Auth::requireAdmin();
        $book = BookService::find((int) $id);
        if (!$book) {
            http_response_code(404);
            echo 'Libro no encontrado';
            return;
        }
        view('books/form', [
            'title' => 'Editar libro',
            'book' => $book,
            'structure' => BookService::formStructure((int) $id),
            'authors' => AuthorService::all(),
            'error' => flash('error'),
        ]);
    }

    public static function update(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        $res = BookService::update((int) $id, self::payload(), self::structureFromPost());
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Libro actualizado.' : ($res['error'] ?? 'No se pudo guardar.'));
        redirect('/libros/' . (int) $id);
    }

    public static function destroy(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        BookService::delete((int) $id);
        flash('success', 'Libro eliminado.');
        redirect('/libros');
    }

    public static function uploadMilestone(string $id): void
    {
        Auth::requireAdmin();
        require_csrf();
        $bookId = (int) $id;
        $kind = str_ends_with(current_route_path(), '/sinopsis')
            ? DocumentService::KIND_SYNOPSIS
            : DocumentService::KIND_OUTLINE;
        $res = DocumentService::storeMilestone(
            $bookId,
            $kind,
            $_FILES['file'] ?? [],
            Auth::user() ? (int) Auth::user()['id'] : null
        );
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Documento cargado.' : ($res['error'] ?? 'No se pudo subir.'));
        $anchor = $kind === DocumentService::KIND_SYNOPSIS ? 'sinopsis' : 'outline';
        redirect('/libros/' . $bookId . '?focus=' . $anchor);
    }

    public static function uploadChapter(string $id, string $chapterId): void
    {
        Auth::requireAdmin();
        require_csrf();
        $bookId = (int) $id;
        $cid = (int) $chapterId;
        $chapters = BookService::chapters($bookId);
        $title = 'Capítulo';
        foreach ($chapters as $ch) {
            if ((int) $ch['id'] === $cid) {
                $title = (string) $ch['title'];
                break;
            }
        }
        $res = DocumentService::storeChapter(
            $bookId,
            $cid,
            $_FILES['file'] ?? [],
            Auth::user() ? (int) Auth::user()['id'] : null,
            $title
        );
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Borrador actualizado. Páginas recalculadas.' : ($res['error'] ?? 'No se pudo subir.'));
        redirect('/libros/' . $bookId . '?focus=capitulo-' . $cid);
    }

    public static function uploadChapterPdf(string $id, string $chapterId): void
    {
        Auth::requireAdmin();
        require_csrf();
        $bookId = (int) $id;
        $cid = (int) $chapterId;
        $chapters = BookService::chapters($bookId);
        $title = 'Capítulo';
        foreach ($chapters as $ch) {
            if ((int) $ch['id'] === $cid) {
                $title = (string) $ch['title'];
                break;
            }
        }
        $res = DocumentService::storePdf(
            $bookId,
            $cid,
            $_FILES['file'] ?? [],
            Auth::user() ? (int) Auth::user()['id'] : null,
            $title
        );
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'PDF de cierre cargado.' : ($res['error'] ?? 'No se pudo subir.'));
        redirect('/libros/' . $bookId . '?focus=capitulo-' . $cid);
    }

    public static function deleteDocument(string $id, string $docId): void
    {
        Auth::requireAdmin();
        require_csrf();
        $doc = DocumentService::find((int) $docId);
        if ($doc && (int) $doc['book_id'] === (int) $id) {
            DocumentService::delete((int) $docId);
            flash('success', 'Archivo eliminado.');
        }
        redirect('/libros/' . (int) $id);
    }

    public static function download(string $id, string $docId): void
    {
        $bookId = (int) $id;
        AccessService::requireBook($bookId);
        $doc = DocumentService::find((int) $docId);
        if (!$doc || (int) $doc['book_id'] !== $bookId) {
            http_response_code(404);
            echo 'Archivo no encontrado';
            return;
        }
        $abs = DocumentService::absolutePath($doc);
        if (!is_file($abs)) {
            http_response_code(404);
            echo 'El archivo ya no está en disco.';
            return;
        }
        $name = (string) ($doc['original_name'] ?? 'documento');
        header('Content-Type: ' . ((string) ($doc['mime_type'] ?? 'application/octet-stream')));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
        exit;
    }

    /** @return array<string,mixed> */
    private static function payload(): array
    {
        return [
            'author_id' => (int) ($_POST['author_id'] ?? 0),
            'title' => post_string('title'),
            'subtitle' => post_string('subtitle'),
            'description' => post_string('description'),
            'genre' => post_string('genre'),
            'language' => post_string('language') ?: 'es',
            'isbn' => post_string('isbn'),
            'status' => post_string('status') ?: 'en_proceso',
        ];
    }

    /** @return array<string,mixed> */
    private static function structureFromPost(): array
    {
        $intro = null;
        if (!empty($_POST['include_intro'])) {
            $title = trim((string) ($_POST['intro_title'] ?? ''));
            $intro = [
                'id' => (int) ($_POST['intro_id'] ?? 0),
                'title' => $title !== '' ? $title : 'Introducción',
            ];
        }

        $epilogue = null;
        if (!empty($_POST['include_epilogue'])) {
            $title = trim((string) ($_POST['epilogue_title'] ?? ''));
            $epilogue = [
                'id' => (int) ($_POST['epilogue_id'] ?? 0),
                'title' => $title !== '' ? $title : 'Epílogo',
            ];
        }

        $parts = [];
        $rawParts = $_POST['parts'] ?? [];
        if (is_array($rawParts)) {
            $pi = 0;
            foreach ($rawParts as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $pi++;
                $ptitle = trim((string) ($part['title'] ?? ''));
                $parts[] = [
                    'id' => (int) ($part['id'] ?? 0),
                    'title' => $ptitle !== '' ? $ptitle : ('Parte ' . format_roman($pi)),
                ];
            }
        }

        $titles = $_POST['chapter_title'] ?? [];
        $ids = $_POST['chapter_id'] ?? [];
        $partIdx = $_POST['chapter_part'] ?? [];
        if (!is_array($titles)) {
            $titles = [];
        }
        if (!is_array($ids)) {
            $ids = [];
        }
        if (!is_array($partIdx)) {
            $partIdx = [];
        }

        $count = (int) ($_POST['chapter_count'] ?? 0);
        if ($count < 1) {
            $count = count($titles);
        }
        $count = min(80, max(1, $count));

        $chapters = [];
        $n = 1;
        for ($i = 0; $i < $count; $i++) {
            $title = trim((string) ($titles[$i] ?? ''));
            $idx = (int) ($partIdx[$i] ?? 0);
            if ($idx < 1 || $idx > count($parts)) {
                $idx = 0;
            }
            $chapters[] = [
                'id' => (int) ($ids[$i] ?? 0),
                'title' => $title !== '' ? $title : ('Capítulo ' . $n),
                'part_index' => $idx,
            ];
            $n++;
        }

        return [
            'intro' => $intro,
            'parts' => $parts,
            'chapters' => $chapters,
            'epilogue' => $epilogue,
        ];
    }
}
