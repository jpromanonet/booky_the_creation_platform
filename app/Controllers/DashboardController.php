<?php

declare(strict_types=1);

final class DashboardController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $authorId = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
        $bookId = isset($_GET['book_id']) ? (int) $_GET['book_id'] : 0;

        $authors = AuthorService::all();
        $books = BookService::all($authorId > 0 ? $authorId : null);
        if ($bookId > 0 && !AccessService::canViewBook($bookId)) {
            $bookId = 0;
        }
        if ($bookId > 0 && $authorId > 0) {
            $candidate = BookService::find($bookId);
            if (!$candidate || (int) $candidate['author_id'] !== $authorId) {
                $bookId = 0;
            }
        }

        $selected = null;
        $progress = null;
        if ($bookId > 0) {
            $selected = BookService::find($bookId);
            if ($selected) {
                $progress = ProgressService::forBook($bookId);
            }
        }

        $catalog = ProgressService::catalog();
        if ($authorId > 0) {
            $catalog = array_values(array_filter(
                $catalog,
                static fn (array $row): bool => (int) $row['author_id'] === $authorId
            ));
        }

        view('dashboard/index', [
            'title' => 'Dashboard',
            'authors' => $authors,
            'books' => $books,
            'authorId' => $authorId,
            'bookId' => $bookId,
            'selected' => $selected,
            'progress' => $progress,
            'catalog' => $catalog,
            'overview' => ProgressService::overview($catalog),
            'success' => flash('success'),
            'error' => flash('error'),
        ]);
    }
}
