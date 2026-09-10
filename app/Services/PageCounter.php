<?php

declare(strict_types=1);

final class PageCounter
{
    /** Palabras aprox. por página A4 de prosa (novela / manuscrito). */
    private const WORDS_PER_PAGE = 280;

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
            'docx' => self::fromDocx($absPath, $originalName !== '' ? $originalName : basename($absPath)),
            'ods', 'odt' => self::fromOpenDocument($absPath, $originalName !== '' ? $originalName : basename($absPath)),
            'pdf' => self::fromPdf($absPath),
            default => 1,
        };
    }

    private static function fromDocx(string $absPath, string $originalName): int
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            return 1;
        }

        $app = (string) $zip->getFromName('docProps/app.xml');
        $doc = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Lo más fiable: saltos que Word dejó al paginar el documento.
        $rendered = preg_match_all('/<w:lastRenderedPageBreak\b/i', $doc);
        if ($rendered > 0) {
            return $rendered + 1;
        }

        $metaPages = self::xmlInt($app, [], 'Pages');
        $words = DocumentText::wordCountFromFile($absPath, $originalName);
        $fromWords = $words > 0 ? max(1, (int) ceil($words / self::WORDS_PER_PAGE)) : 1;

        $explicit = preg_match_all('/w:type\s*=\s*["\']page["\']/i', $doc);
        $fromBreaks = max(1, $explicit + 1);

        // app.xml a veces trae un Pages viejo/chico (ej. 9). Solo se usa si no contradice el texto.
        if ($metaPages !== null && $metaPages > 0) {
            if ($fromWords <= 12 || $metaPages >= (int) floor($fromWords * 0.55)) {
                return max($metaPages, $fromBreaks);
            }
        }

        return max($fromWords, $fromBreaks);
    }

    private static function fromOpenDocument(string $absPath, string $originalName): int
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath) !== true) {
            return 1;
        }

        $meta = (string) $zip->getFromName('meta.xml');
        $metaPages = null;
        if (preg_match('/meta:page-count="(\d+)"/', $meta, $m) && (int) $m[1] > 0) {
            $metaPages = (int) $m[1];
        }

        $content = (string) $zip->getFromName('content.xml');
        $zip->close();

        $words = DocumentText::wordCountFromFile($absPath, $originalName);
        $fromWords = $words > 0 ? max(1, (int) ceil($words / self::WORDS_PER_PAGE)) : 1;
        $pageBreaks = preg_match_all('/break-before="page"|fo:break-before="page"/', $content);
        $fromBreaks = $pageBreaks > 0 ? $pageBreaks + 1 : 1;

        if ($metaPages !== null) {
            if ($fromWords <= 12 || $metaPages >= (int) floor($fromWords * 0.55)) {
                return max($metaPages, $fromBreaks);
            }
        }

        if ($pageBreaks > 0) {
            return max($fromBreaks, $fromWords);
        }

        $rows = preg_match_all('/<table:table-row[\s>]/', $content);
        if ($rows > 40) {
            return max($fromWords, (int) ceil($rows / 40));
        }

        return max(1, $fromWords);
    }

    private static function fromPdf(string $absPath): int
    {
        $raw = (string) @file_get_contents($absPath);
        if ($raw === '') {
            return 1;
        }
        if (preg_match_all('#/Count\s+(\d+)#', $raw, $m) && $m[1] !== []) {
            $max = max(array_map('intval', $m[1]));
            if ($max > 0) {
                return $max;
            }
        }
        $n = preg_match_all('#/Type\s*/Page(?!\s*/)#', $raw);
        return max(1, (int) $n);
    }

    private static function xmlInt(string $xml, array $unused, string $tag): ?int
    {
        unset($unused);
        if ($xml === '' || $tag === '') {
            return null;
        }
        $safe = preg_quote($tag, '#');
        if (preg_match('#<' . $safe . '[^>]*>(\d+)</' . $safe . '>#i', $xml, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
