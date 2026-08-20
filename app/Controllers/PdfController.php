<?php

declare(strict_types=1);

final class PdfController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $rows = [];
        foreach (ProgressService::catalog() as $book) {
            $id = (int) $book['id'];
            $ready = ManuscriptPdfService::readiness($id);
            $rows[] = array_merge($book, ['pdf' => $ready]);
        }
        view('pdf/index', [
            'title' => 'PDF',
            'rows' => $rows,
        ]);
    }

    public static function download(string $id): void
    {
        $bookId = (int) $id;
        AccessService::requireBook($bookId);
        if (!BookService::find($bookId)) {
            http_response_code(404);
            echo 'Libro no encontrado';
            return;
        }
        $res = ManuscriptPdfService::stream($bookId);
        if (!$res['ok']) {
            flash('error', $res['error'] ?? 'No se pudo generar el PDF.');
            redirect('/pdf');
        }
    }
}
