<?php

declare(strict_types=1);

final class ManuscriptPdfService
{
    /**
     * @return array{ready:int,total:int,missing:list<string>}
     */
    public static function readiness(int $bookId): array
    {
        $missing = [];
        $ready = 0;
        $total = 0;
        foreach (BookService::groupedSections($bookId) as $group) {
            foreach ($group['items'] as $ch) {
                $total++;
                if (!empty($ch['pdf_id'])) {
                    $ready++;
                } else {
                    $missing[] = (string) ($ch['title'] ?? 'Sección');
                }
            }
        }
        return ['ready' => $ready, 'total' => $total, 'missing' => $missing];
    }

    /** @return array{ok:bool,error?:string} */
    public static function stream(int $bookId): array
    {
        $book = BookService::find($bookId);
        if (!$book) {
            return ['ok' => false, 'error' => 'Libro no encontrado.'];
        }

        @set_time_limit(300);
        @ini_set('display_errors', '0');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $title = (string) $book['title'];
        $author = (string) ($book['author_name'] ?? '');
        $subtitle = trim((string) ($book['subtitle'] ?? ''));
        $plan = self::plan($bookId);
        if ($plan['files'] === 0) {
            return ['ok' => false, 'error' => 'No hay PDF de manuscrito cargados. Subí el PDF de cada sección cuando esté lista.'];
        }

        $tmp = self::tmpDir();
        $stampedPaths = [];
        $pageNum = 1;
        $seq = 0;
        try {
            $tocCount = self::tocPageCount($title, $author, $plan['entries']);
            $offset = 1 + $tocCount;

            $cover = new SimplePdf();
            $cover->setChrome($title, $author);
            $cover->setHeaderRight('');
            $cover->setPageNumberStart($pageNum);
            $cover->setDrawChrome(true);
            $cover->titlePage($title, $author, $subtitle);
            $coverPath = $tmp . '/cover.pdf';
            $cover->saveTo($coverPath);
            $stampedPaths[] = $coverPath;
            $pageNum += max(1, $cover->pageCount());

            $index = new SimplePdf();
            $index->setChrome($title, $author);
            $index->setHeaderRight('Índice');
            $index->setPageNumberStart($pageNum);
            $index->setDrawChrome(true);
            $index->addPage();
            self::writeIndex($index, $plan['entries'], $offset);
            $indexPath = $tmp . '/index.pdf';
            $index->saveTo($indexPath);
            $stampedPaths[] = $indexPath;
            $pageNum += max(1, $index->pageCount());

            foreach ($plan['pieces'] as $piece) {
                if (($piece['type'] ?? '') === 'part') {
                    $p = new SimplePdf();
                    $p->setChrome($title, $author);
                    $p->setHeaderRight((string) $piece['title']);
                    $p->setPageNumberStart($pageNum);
                    $p->setDrawChrome(true);
                    $p->partPage((int) $piece['number'], (string) $piece['title']);
                    $partPath = $tmp . '/part-' . (int) $piece['number'] . '.pdf';
                    $p->saveTo($partPath);
                    $stampedPaths[] = $partPath;
                    $pageNum += max(1, $p->pageCount());
                    continue;
                }

                $fileRes = self::stampPiece(
                    $tmp,
                    $title,
                    $author,
                    (string) $piece['path'],
                    (string) ($piece['right'] ?? ''),
                    $pageNum,
                    $seq++
                );
                if (!$fileRes['ok']) {
                    self::cleanup($tmp);
                    $label = (string) ($piece['right'] ?? 'sección');
                    return ['ok' => false, 'error' => ($fileRes['error'] ?? 'No se pudo sellar.') . ' (' . $label . ')'];
                }
                $stampedPaths[] = (string) $fileRes['path'];
                $pageNum += (int) $fileRes['pages'];
            }

            $outPath = $tmp . '/book.pdf';
            $merged = PdfMergeService::merge($stampedPaths, $outPath);
            if (!$merged['ok']) {
                self::cleanup($tmp);
                return ['ok' => false, 'error' => $merged['error'] ?? 'No se pudo empalmar.'];
            }

            $bytes = (string) file_get_contents($outPath);
            $name = self::filename($title);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Cache-Control: no-store');
            header('Content-Length: ' . (string) strlen($bytes));
            echo $bytes;
            self::cleanup($tmp);
            exit;
        } catch (Throwable $e) {
            self::cleanup($tmp);
            return ['ok' => false, 'error' => 'No se pudo armar el PDF.'];
        }
    }

    /**
     * @return array{ok:bool,error?:string,path?:string,pages?:int}
     */
    private static function stampPiece(
        string $tmp,
        string $title,
        string $author,
        string $basePath,
        string $headerRight,
        int $pageStart,
        int $seq
    ): array {
        $normalized = $tmp . '/a4-' . $seq . '.pdf';
        $norm = PdfMergeService::flattenA4($basePath, $normalized);
        if (!$norm['ok']) {
            return ['ok' => false, 'error' => $norm['error'] ?? 'No se pudo normalizar el PDF a A4.'];
        }
        $working = (string) ($norm['path'] ?? $normalized);

        $pages = max(1, PdfMergeService::pageCount($working));
        $stampPath = $tmp . '/stamp-' . $seq . '.pdf';
        self::writeSectionStamp($stampPath, $title, $author, $headerRight, $pages, $pageStart);

        $outPath = $tmp . '/stamped-' . $seq . '.pdf';
        $stamped = PdfMergeService::overlay($working, $stampPath, $outPath);
        if ($stamped['ok'] && PdfMergeService::hasChromeMarker($outPath)) {
            return ['ok' => true, 'path' => $outPath, 'pages' => $pages];
        }

        $paged = PdfMergeService::overlayPageByPage($working, $stampPath, $outPath, $tmp . '/pg-' . $seq);
        if (!$paged['ok']) {
            return ['ok' => false, 'error' => $paged['error'] ?? ($stamped['error'] ?? 'No se pudo sellar encabezado y pie.')];
        }
        return ['ok' => true, 'path' => $outPath, 'pages' => $pages];
    }

    /**
     * @return array{files:int,entries:list<array{label:string,page:int,bold:bool,level:int}>,pieces:list<array<string,mixed>>}
     */
    private static function plan(int $bookId): array
    {
        $pieces = [];
        $entries = [];
        $page = 1;
        $partNum = 0;
        $files = 0;

        foreach (BookService::groupedSections($bookId) as $group) {
            $kind = (string) ($group['kind'] ?? '');
            $heading = (string) ($group['heading'] ?? '');
            $withPdf = [];
            foreach ($group['items'] as $ch) {
                if (!empty($ch['pdf_id']) && !empty($ch['pdf_path'])) {
                    $withPdf[] = $ch;
                }
            }
            if ($withPdf === []) {
                continue;
            }
            if ($kind === 'part') {
                $partNum++;
                $partTitle = $heading !== '' ? $heading : ('Parte ' . $partNum);
                $entries[] = ['label' => $partTitle, 'page' => $page, 'bold' => true, 'level' => 0];
                $pieces[] = ['type' => 'part', 'number' => $partNum, 'title' => $partTitle];
                $page += 1;
            }
            foreach ($withPdf as $ch) {
                $doc = DocumentService::find((int) $ch['pdf_id']);
                $abs = $doc ? DocumentService::absolutePath($doc) : '';
                if ($abs === '' || !is_file($abs)) {
                    continue;
                }
                $pages = max(1, PdfMergeService::pageCount($abs));
                $chKind = (string) ($ch['kind'] ?? 'chapter');
                $label = (string) ($ch['title'] ?? '');
                $level = ($kind === 'part' && $chKind === 'chapter') ? 1 : 0;
                $bold = $chKind !== 'chapter';
                $entries[] = ['label' => $label, 'page' => $page, 'bold' => $bold, 'level' => $level];
                $pieces[] = ['type' => 'file', 'path' => $abs, 'right' => $label];
                $page += $pages;
                $files++;
            }
        }

        return ['files' => $files, 'entries' => $entries, 'pieces' => $pieces];
    }

    /**
     * @param list<array{label:string,page:int,bold:bool,level:int}> $entries
     */
    private static function writeIndex(SimplePdf $pdf, array $entries, int $offset): void
    {
        $pdf->indexTitle();
        foreach ($entries as $entry) {
            $pdf->indexLine(
                (string) $entry['label'],
                $offset + (int) $entry['page'],
                (bool) $entry['bold'],
                (int) $entry['level']
            );
        }
    }

    /**
     * @param list<array{label:string,page:int,bold:bool,level:int}> $entries
     */
    private static function tocPageCount(string $title, string $author, array $entries): int
    {
        unset($title, $author);
        $count = 1;
        for ($i = 0; $i < 6; $i++) {
            $probe = new SimplePdf();
            $probe->setDrawChrome(false);
            $probe->addPage();
            self::writeIndex($probe, $entries, 1 + $count);
            $n = max(1, $probe->pageCount());
            if ($n === $count) {
                return $count;
            }
            $count = $n;
        }
        return $count;
    }

    private static function writeSectionStamp(
        string $path,
        string $title,
        string $author,
        string $headerRight,
        int $pages,
        int $pageStart
    ): void {
        $pages = max(1, $pages);
        $pdf = new SimplePdf();
        $pdf->setChrome($title, $author);
        $pdf->setDrawChrome(true);
        $pdf->setPageNumberStart($pageStart);
        for ($i = 0; $i < $pages; $i++) {
            $pdf->blankPage($headerRight);
        }
        $pdf->saveTo($path);
    }

    private static function tmpDir(): string
    {
        $root = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($root)) {
            @mkdir($root, 0777, true);
        }
        $dir = $root . '/' . bin2hex(random_bytes(8));
        @mkdir($dir, 0777, true);
        return $dir;
    }

    private static function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    private static function filename(string $title): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: $title;
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $slug));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'libro';
        }
        return $slug . '.pdf';
    }
}
