<?php

declare(strict_types=1);

final class DocumentText
{
    public static function fromDocument(array $doc): string
    {
        $abs = DocumentService::absolutePath($doc);
        $name = (string) ($doc['original_name'] ?? $abs);
        return self::fromPath($abs, $name);
    }

    public static function fromPath(string $abs, string $originalName = ''): string
    {
        $name = $originalName !== '' ? $originalName : $abs;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!is_file($abs) || !class_exists('ZipArchive')) {
            return '';
        }
        return match ($ext) {
            'docx' => self::fromDocx($abs),
            'odt', 'ods' => self::fromOpenDocument($abs),
            default => '',
        };
    }

    public static function wordCountFromFile(string $abs, string $originalName = ''): int
    {
        return self::countWords(self::fromPath($abs, $originalName));
    }

    public static function countWords(string $text): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }

    private static function fromDocx(string $abs): string
    {
        $zip = new ZipArchive();
        if ($zip->open($abs) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $dom = self::dom($xml);
        if (!$dom) {
            return self::plainFallback($xml);
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paras = [];
        foreach ($xp->query('//w:body/w:p') ?: [] as $p) {
            $paras[] = trim(self::nodeText($p));
        }
        if ($paras === []) {
            foreach ($xp->query('//w:p') ?: [] as $p) {
                $paras[] = trim(self::nodeText($p));
            }
        }
        return self::join($paras);
    }

    private static function fromOpenDocument(string $abs): string
    {
        $zip = new ZipArchive();
        if ($zip->open($abs) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('content.xml');
        $zip->close();
        $dom = self::dom($xml);
        if (!$dom) {
            return self::plainFallback($xml);
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('text', 'urn:oasis:names:opendocument:xmlns:text:1.0');
        $xp->registerNamespace('office', 'urn:oasis:names:opendocument:xmlns:office:1.0');
        $paras = [];
        foreach ($xp->query('//text:h|//text:p') ?: [] as $p) {
            $paras[] = trim(self::nodeText($p));
        }
        return self::join($paras);
    }

    private static function dom(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $ok ? $dom : null;
    }

    private static function nodeText(DOMNode $node): string
    {
        $text = $node->textContent ?? '';
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function plainFallback(string $xml): string
    {
        $xml = preg_replace('#<w:tab\s*/>#', ' ', $xml) ?? $xml;
        $xml = preg_replace('#<w:br\b[^>]*/>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<text:line-break\s*/>#', "\n", $xml) ?? $xml;
        $plain = preg_replace('#<[^>]+>#', "\n", $xml) ?? '';
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $lines = preg_split('/\n+/', $plain) ?: [];
        return self::join(array_map('trim', $lines));
    }

    /** @param list<string> $paras */
    private static function join(array $paras): string
    {
        $out = [];
        $blank = false;
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p === '') {
                if (!$blank && $out !== []) {
                    $out[] = '';
                    $blank = true;
                }
                continue;
            }
            $blank = false;
            $out[] = $p;
        }
        return trim(implode("\n\n", $out));
    }
}
