<?php

declare(strict_types=1);

final class MilestoneController
{
    public static function index(): void
    {
        Auth::requireLogin();
        $authorId = isset($_GET['author_id']) ? (int) $_GET['author_id'] : 0;
        $catalog = ProgressService::catalog();
        if ($authorId > 0) {
            $catalog = array_values(array_filter(
                $catalog,
                static fn (array $row): bool => (int) ($row['author_id'] ?? 0) === $authorId
            ));
        }

        $pack = MilestoneCoachService::build($catalog);
        $overview = ProgressService::overview($catalog);
        $author = $authorId > 0 ? AuthorService::find($authorId) : null;

        view('milestones/index', [
            'title' => 'Milestones',
            'hero' => $pack['hero'],
            'cards' => $pack['cards'],
            'overview' => $overview,
            'authors' => AuthorService::all(),
            'authorId' => $authorId,
            'authorName' => $author ? (string) $author['name'] : '',
        ]);
    }
}
