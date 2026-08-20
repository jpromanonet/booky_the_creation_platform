<?php

declare(strict_types=1);

final class PageCounter
{
    public static function count(string $absPath, string $originalName = ''): int
    {
        if (!is_file($absPath)) {
            return 0;
        }
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $absPath, PATHINFO_EXTENSION));
        if (!class_exists('ZipArchive')) {
            return 1;
        }

        return match ($ext) {
            'docx' => self::fromDocx($absPath),
            'ods', 'odt' => self::fromOpenDocument($absPath),
            'pdf' => self::fromPdf($absPath),
            default => 1,
        };
    }

    private static function fromDocx(string $absPath): int
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            return 1;
        }

        $app = (string) $zip->getFromName('docProps/app.xml');
        $pages = self::xmlInt($app, ['<Pages>', '<Pages xmlns'], 'Pages');
        if ($pages === null) {
            if (preg_match('/<Pages>(\d+)<\/Pages>/i', $app, $m)) {
                $pages = (int) $m[1];
            }
        }
        if ($pages !== null && $pages > 0) {
            $zip->close();
            return $pages;
        }

        $doc = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $breaks = preg_match_all('/w:type="page"|lastRenderedPageBreak/i', $doc);
        $words = str_word_count(strip_tags($doc));
        $fromBreaks = max(1, $breaks + 1);
        $fromWords = max(1, (int) ceil($words / 280));
        return max($fromBreaks, min($fromWords, $fromBreaks + 8));
    }

    private static function fromOpenDocument(string $absPath): int
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            return 1;
        }

        $meta = (string) $zip->getFromName('meta.xml');
        if (preg_match('/meta:page-count="(\d+)"/', $meta, $m) && (int) $m[1] > 0) {
            $pages = (int) $m[1];
            $zip->close();
            return $pages;
        }

        $content = (string) $zip->getFromName('content.xml');
        $zip->close();
        $tables = preg_match_all('/<table:table[\s>]/', $content);
        $pageBreaks = preg_match_all('/break-before="page"|fo:break-before="page"/', $content);
        if ($pageBreaks > 0) {
            return $pageBreaks + 1;
        }
        $rows = preg_match_all('/<table:table-row[\s>]/', $content);
        if ($rows > 0) {
            return max(1, (int) ceil($rows / 40));
        }
        $words = str_word_count(strip_tags($content));
        if ($words > 0) {
            return max(1, (int) ceil($words / 280));
        }
        return max(1, $tables);
    }

    private static function fromPdf(string $absPath): int
    {
        $raw = (string) @file_get_contents($absPath);
        if ($raw === '') {
            return 1;
        }
        if (preg_match_all('/\/Count\s+(\d+)/', $raw, $m) && $m[1] !== []) {
            $max = max(array_map('intval', $m[1]));
            if ($max > 0) {
                return $max;
            }
        }
        $n = preg_match_all('/\/Type\s*\/Page(?!\s*\/)/', $raw);
        return max(1, (int) $n);
    }

    private static function xmlInt(string $xml, array $unused, string $tag): ?int
    {
        unset($unused);
        if ($xml === '') {
            return null;
        }
        if (preg_match('/<' . preg_quote($tag, '/') . '[^>]*>(\d+)<\//' . preg_quote($tag, '/') . '>/i', $xml, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
