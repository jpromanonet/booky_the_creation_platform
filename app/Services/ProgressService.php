<?php

declare(strict_types=1);

final class ProgressService
{
    public const COLOR_LIGHT = '#4ade80';
    public const COLOR_DARK = '#166534';
    public const COLOR_MID = '#d97706';
    public const COLOR_LOW = '#ef4444';

    /** Verde clarito desde 50%, verde oscuro al 100%. Debajo: ámbar / rojo. */
    public static function barColor(float $pct): string
    {
        if ($pct >= 99.9) {
            return self::COLOR_DARK;
        }
        if ($pct >= 50) {
            return self::COLOR_LIGHT;
        }
        if ($pct >= 34) {
            return self::COLOR_MID;
        }
        return self::COLOR_LOW;
    }

    /**
     * @return array<string,mixed>
     */
    public static function forBook(int $bookId): array
    {
        $book = BookService::find($bookId);
        $chapters = BookService::chapters($bookId);
        $outline = DocumentService::milestone($bookId, DocumentService::KIND_OUTLINE);
        $synopsis = DocumentService::milestone($bookId, DocumentService::KIND_SYNOPSIS);

        $hasOutline = $outline !== null;
        $hasSynopsis = $synopsis !== null;
        $chaptersTotal = count($chapters);
        $chaptersDone = 0;
        $pages = 0;
        $words = 0;
        $palette = ['#9a3412', '#d97706', '#3b82f6', '#7c3aed', '#0f766e', '#db2777', '#f97316', '#2563eb'];
        $segments = [];
        $cumulative = [];
        $running = 0;

        foreach ($chapters as $i => $ch) {
            $name = strtolower((string) ($ch['original_name'] ?? ''));
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $chPages = $ext === 'pdf' ? 0 : self::chapterPages($ch);
            $chWords = $ext === 'pdf' ? 0 : self::chapterWords($ch);
            $done = !empty($ch['document_id']);
            if ($done) {
                $chaptersDone++;
            }
            $pages += $chPages;
            $words += $chWords;
            $running += $chPages;
            $kind = (string) ($ch['kind'] ?? 'chapter');
            $label = (string) $ch['title'];
            $partTitle = trim((string) ($ch['part_title'] ?? ''));
            if ($kind === 'chapter' && $partTitle !== '') {
                $label = $partTitle . ' · ' . $label;
            }
            $segments[] = [
                'label' => $label,
                'pages' => $chPages,
                'words' => $chWords,
                'pct' => 0.0,
                'color' => $palette[$i % count($palette)],
                'done' => $done,
                'kind' => $kind,
                'chapter_id' => (int) ($ch['id'] ?? 0),
                'uploaded_at' => (string) ($ch['uploaded_at'] ?? ''),
            ];
            $cumulative[] = ['label' => $label, 'pages' => $running];
        }

        $totalPages = max(0, $pages);
        if ($totalPages > 0) {
            foreach ($segments as &$seg) {
                $seg['pct'] = round(($seg['pages'] / $totalPages) * 100, 1);
            }
            unset($seg);
        } elseif ($segments !== []) {
            $share = round(100 / count($segments), 1);
            foreach ($segments as &$seg) {
                $seg['pct'] = $share;
            }
            unset($seg);
        }

        $total = 2 + max(1, $chaptersTotal);
        $done = ($hasOutline ? 1 : 0) + ($hasSynopsis ? 1 : 0) + $chaptersDone;
        $pct = round(($done / $total) * 100, 1);
        $chapterPct = $chaptersTotal > 0 ? round(($chaptersDone / $chaptersTotal) * 100, 1) : 0.0;
        $color = self::barColor($pct);
        $pageValues = array_map(static fn (array $s): int => (int) $s['pages'], $segments);
        $withPages = array_values(array_filter($pageValues, static fn (int $n): bool => $n > 0));
        $lastUpload = null;
        foreach ($chapters as $ch) {
            $at = trim((string) ($ch['uploaded_at'] ?? ''));
            if ($at !== '' && ($lastUpload === null || $at > $lastUpload)) {
                $lastUpload = $at;
            }
        }

        return [
            'pct' => $pct,
            'done' => $done,
            'total' => $total,
            'has_outline' => $hasOutline,
            'has_synopsis' => $hasSynopsis,
            'chapters_done' => $chaptersDone,
            'chapters_pending' => max(0, $chaptersTotal - $chaptersDone),
            'chapters_total' => $chaptersTotal,
            'chapter_pct' => $chapterPct,
            'pages' => $pages,
            'words' => $words,
            'avg_pages' => $chaptersDone > 0 ? round($pages / $chaptersDone, 1) : 0,
            'max_pages' => $withPages !== [] ? max($withPages) : 0,
            'min_pages' => $withPages !== [] ? min($withPages) : 0,
            'outline_pages' => (int) ($outline['page_count'] ?? 0),
            'synopsis_pages' => (int) ($synopsis['page_count'] ?? 0),
            'segments' => $segments,
            'cumulative' => $cumulative,
            'color' => $color,
            'genre' => (string) ($book['genre'] ?? ''),
            'status' => (string) ($book['status'] ?? ''),
            'author' => (string) ($book['author_name'] ?? ''),
            'title' => (string) ($book['title'] ?? ''),
            'last_upload_at' => $lastUpload,
            'complete' => $pct >= 99.9,
        ];
    }

    /** @param array<string,mixed> $ch */
    private static function chapterPages(array $ch): int
    {
        if (empty($ch['document_id'])) {
            return 0;
        }
        $doc = DocumentService::find((int) $ch['document_id']);
        if (!$doc) {
            return max(0, (int) ($ch['page_count'] ?? 0));
        }
        $abs = DocumentService::absolutePath($doc);
        $name = (string) ($doc['original_name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'pdf' || !is_file($abs)) {
            return max(0, (int) ($ch['page_count'] ?? 0));
        }
        $n = PageCounter::count($abs, $name);
        $stored = (int) ($ch['page_count'] ?? 0);
        if ($n > 0 && $n !== $stored) {
            try {
                Database::pdo()->prepare('UPDATE documents SET page_count = :p WHERE id = :id')
                    ->execute(['p' => $n, 'id' => (int) $doc['id']]);
            } catch (Throwable) {
            }
        }
        return max(0, $n);
    }

    /** @param array<string,mixed> $ch */
    private static function chapterWords(array $ch): int
    {
        $stored = (int) ($ch['word_count'] ?? 0);
        if ($stored > 0 || empty($ch['document_id'])) {
            return max(0, $stored);
        }
        $doc = DocumentService::find((int) $ch['document_id']);
        if (!$doc) {
            return 0;
        }
        $abs = DocumentService::absolutePath($doc);
        $name = (string) ($doc['original_name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'pdf' || !is_file($abs)) {
            return 0;
        }
        $n = DocumentText::wordCountFromFile($abs, $name);
        if ($n > 0) {
            try {
                Database::pdo()->prepare('UPDATE documents SET word_count = :w WHERE id = :id')
                    ->execute(['w' => $n, 'id' => (int) $doc['id']]);
            } catch (Throwable) {
                // Columna puede faltar en un instante raro; igual devolvemos el conteo.
            }
        }
        return $n;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function catalog(): array
    {
        $rows = [];
        foreach (BookService::all() as $book) {
            $progress = self::forBook((int) $book['id']);
            $rows[] = array_merge($book, ['progress' => $progress]);
        }
        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $catalog
     * @return array<string,mixed>
     */
    public static function overview(array $catalog): array
    {
        $books = count($catalog);
        $pages = 0;
        $words = 0;
        $chaptersDone = 0;
        $chaptersTotal = 0;
        $outline = 0;
        $synopsis = 0;
        $complete = 0;
        $overHalf = 0;
        $pctSum = 0.0;
        $hitDone = 0;
        $hitTotal = 0;
        $byAuthor = [];
        $pagesByAuthor = [];
        $pctSumByAuthor = [];
        $booksByAuthor = [];
        $byStatus = [];
        $byGenre = [];
        $pctBuckets = ['0–25' => 0, '26–50' => 0, '51–75' => 0, '76–100' => 0];

        foreach ($catalog as $row) {
            $p = $row['progress'] ?? [];
            $pages += (int) ($p['pages'] ?? 0);
            $words += (int) ($p['words'] ?? 0);
            $chaptersDone += (int) ($p['chapters_done'] ?? 0);
            $chaptersTotal += (int) ($p['chapters_total'] ?? 0);
            $outline += !empty($p['has_outline']) ? 1 : 0;
            $synopsis += !empty($p['has_synopsis']) ? 1 : 0;
            $pct = (float) ($p['pct'] ?? 0);
            $pctSum += $pct;
            $hitDone += (int) ($p['done'] ?? 0);
            $hitTotal += (int) ($p['total'] ?? 0);
            if ($pct >= 50) {
                $overHalf++;
            }
            if ($pct >= 99.9) {
                $complete++;
            }
            $author = (string) ($row['author_name'] ?? '—');
            $byAuthor[$author] = ($byAuthor[$author] ?? 0) + 1;
            $pagesByAuthor[$author] = ($pagesByAuthor[$author] ?? 0) + (int) ($p['pages'] ?? 0);
            $pctSumByAuthor[$author] = ($pctSumByAuthor[$author] ?? 0) + $pct;
            $booksByAuthor[$author] = ($booksByAuthor[$author] ?? 0) + 1;
            $status = (string) ($row['status'] ?? 'en_proceso');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $genre = trim((string) ($row['genre'] ?? '')) ?: 'Sin género';
            $byGenre[$genre] = ($byGenre[$genre] ?? 0) + 1;
            if ($pct <= 25) {
                $pctBuckets['0–25']++;
            } elseif ($pct <= 50) {
                $pctBuckets['26–50']++;
            } elseif ($pct <= 75) {
                $pctBuckets['51–75']++;
            } else {
                $pctBuckets['76–100']++;
            }
        }

        $pctByAuthor = [];
        foreach ($pctSumByAuthor as $name => $sum) {
            $n = max(1, (int) ($booksByAuthor[$name] ?? 1));
            $pctByAuthor[$name] = round($sum / $n, 1);
        }

        $overallPct = $hitTotal > 0
            ? round(($hitDone / $hitTotal) * 100, 1)
            : ($books > 0 ? round($pctSum / $books, 1) : 0.0);

        return [
            'books' => $books,
            'pages' => $pages,
            'words' => $words,
            'avg_pages' => $books > 0 ? round($pages / $books, 1) : 0,
            'avg_pct' => $books > 0 ? round($pctSum / $books, 1) : 0,
            'overall_pct' => $overallPct,
            'overall_done' => $hitDone,
            'overall_total' => $hitTotal,
            'overall_color' => self::barColor($overallPct),
            'over_half' => $overHalf,
            'chapters_done' => $chaptersDone,
            'chapters_total' => $chaptersTotal,
            'outline' => $outline,
            'synopsis' => $synopsis,
            'complete' => $complete,
            'in_progress' => max(0, $books - $complete),
            'authors' => count($byAuthor),
            'by_author' => $byAuthor,
            'pages_by_author' => $pagesByAuthor,
            'pct_by_author' => $pctByAuthor,
            'by_status' => $byStatus,
            'by_genre' => $byGenre,
            'pct_buckets' => $pctBuckets,
        ];
    }
}
