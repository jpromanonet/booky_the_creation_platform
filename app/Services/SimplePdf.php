<?php

declare(strict_types=1);

/**
 * PDF A4 con Times, encabezado y pie. Cadenas en hexadecimal (WinAnsi) para no romper el archivo.
 */
final class SimplePdf
{
    private const PAGE_W = 595.28;
    private const PAGE_H = 841.89;

    private string $headerLeft = '';
    private string $footerLeft = '';
    private string $headerRight = '';
    private bool $drawChrome = true;
    private int $pageNumberStart = 1;
    private float $marginL = 68.0;
    private float $marginR = 62.0;
    private float $marginT = 62.0;
    private float $marginB = 58.0;
    private float $size = 11.0;
    private string $style = '';
    private float $y = 0.0;
    private bool $indentNext = false;
    /** @var list<array{content:string,right:string}> */
    private array $pages = [];
    private string $buf = '';

    public function setChrome(string $headerLeft, string $footerLeft): void
    {
        $this->headerLeft = $this->clip($headerLeft, 42);
        $this->footerLeft = $this->clip($footerLeft, 48);
    }

    public function setHeaderRight(string $text): void
    {
        $this->headerRight = $this->clip($text, 40);
    }

    public function setDrawChrome(bool $draw): void
    {
        $this->drawChrome = $draw;
    }

    public function setPageNumberStart(int $page): void
    {
        $this->pageNumberStart = max(1, $page);
    }

    public function blankPage(string $headerRight = ''): void
    {
        $this->flushPage();
        $this->pages[] = ['content' => '', 'right' => $this->clip($headerRight, 40)];
        $this->buf = '';
    }

    public function pageNow(): int
    {
        return count($this->pages) + 1;
    }

    public function pageCount(): int
    {
        return count($this->pages) + ($this->buf !== '' ? 1 : 0);
    }

    /**
     * @return list<array{content:string,right:string}>
     */
    public function exportPages(): array
    {
        $this->flushPage();
        return $this->pages;
    }

    /**
     * @param list<array{content:string,right:string}> $pages
     */
    public function outputPages(array $pages, string $filename): never
    {
        $this->pages = $pages;
        $this->buf = '';
        $this->output($filename);
    }

    public function addPage(): void
    {
        $this->flushPage();
        $this->buf = '';
        $this->y = $this->marginT;
        $this->indentNext = false;
    }

    public function space(float $pt): void
    {
        $this->y += $pt;
        if ($this->y > $this->bottomLimit()) {
            $this->addPage();
        }
    }

    public function setFont(string $style, float $size): void
    {
        $this->style = $style;
        $this->size = $size;
    }

    public function ensure(float $needed): void
    {
        if ($this->y + $needed > $this->bottomLimit()) {
            $this->addPage();
        }
    }

    public function headingBlock(string $kicker, string $title): void
    {
        $this->ensure(88);
        if ($kicker !== '') {
            $this->setFont('I', 9);
            $this->fill(0.35, 0.35, 0.35);
            $this->line($this->marginL, $this->clip($kicker, 90));
            $this->fill(0, 0, 0);
            $this->y += 14;
        }
        $this->setFont('B', 16);
        $this->indentNext = false;
        $this->write($title, 21);
        $this->y += 4;
        $y = $this->pdfY($this->y);
        $this->buf .= sprintf("q 0.25 0.25 0.25 RG 0.5 w %.2f %.2f m %.2f %.2f l S Q\n", $this->marginL, $y, $this->marginL + 46, $y);
        $this->y += 18;
        $this->setFont('', 11);
        $this->indentNext = false;
    }

    public function indexTitle(): void
    {
        $this->ensure(70);
        $this->setFont('B', 18);
        $this->indentNext = false;
        $this->write('Índice', 24);
        $this->y += 6;
        $y = $this->pdfY($this->y);
        $this->buf .= sprintf("q 0.25 0.25 0.25 RG 0.5 w %.2f %.2f m %.2f %.2f l S Q\n", $this->marginL, $y, $this->marginL + 46, $y);
        $this->y += 18;
        $this->setFont('', 11);
    }

    public function indexLine(string $label, int $page, bool $bold = false, int $level = 0): void
    {
        $this->ensure(20);
        $this->setFont($bold ? 'B' : '', 11);
        $indent = $level * 18;
        $num = (string) $page;
        $nw = $this->widthOf($num, 11, $bold ? 'B' : '');
        $maxLabel = $this->contentWidth() - $indent - $nw - 30;
        $label = $this->clipToWidth($label, $maxLabel);
        $lw = $this->widthOf($label);
        $baseline = $this->pdfY($this->y + 11);
        $x = $this->marginL + $indent;
        $this->buf .= $this->tj($x, $baseline, $label);
        $x0 = $x + $lw + 8;
        $x1 = $this->marginL + $this->contentWidth() - $nw - 8;
        if ($x1 > $x0 + 8) {
            $this->buf .= sprintf("q 0.72 0.72 0.72 RG 0.35 w %.2f %.2f m %.2f %.2f l S Q\n", $x0, $baseline + 1, $x1, $baseline + 1);
        }
        $this->buf .= $this->tj($this->marginL + $this->contentWidth() - $nw, $baseline, $num);
        $this->y += 17;
        $this->setFont('', 11);
    }

    public function partPage(int $number, string $title): void
    {
        $this->addPage();
        $this->y = 300;
        $this->setFont('I', 11);
        $this->fill(0.25, 0.25, 0.25);
        $this->textCenter('Parte ' . $number);
        $this->fill(0, 0, 0);
        $this->y += 22;
        $this->setFont('B', 26);
        $this->writeCenteredWrapped($title !== '' ? $title : ('Parte ' . $number), 32);
        $this->y += 18;
        $y = $this->pdfY($this->y);
        $cx = self::PAGE_W / 2;
        $this->buf .= sprintf("q 0.2 0.2 0.2 RG 0.4 w %.2f %.2f m %.2f %.2f l S Q\n", $cx - 28, $y, $cx + 28, $y);
    }

    public function titlePage(string $title, string $author, string $subtitle = ''): void
    {
        $this->addPage();
        $this->y = 250;
        $this->setFont('B', 26);
        $this->writeCenteredWrapped($title, 32);
        if ($subtitle !== '') {
            $this->y += 16;
            $this->setFont('I', 13);
            $this->fill(0.2, 0.2, 0.2);
            $this->writeCenteredWrapped($subtitle, 18);
            $this->fill(0, 0, 0);
        }
        $this->y += 36;
        $y = $this->pdfY($this->y);
        $cx = self::PAGE_W / 2;
        $this->buf .= sprintf("q 0.2 0.2 0.2 RG 0.4 w %.2f %.2f m %.2f %.2f l S Q\n", $cx - 36, $y, $cx + 36, $y);
        $this->y += 28;
        $this->setFont('', 12);
        $this->textCenter($author);
    }

    public function write(string $text, float $leading = 0.0): void
    {
        $leading = $leading > 0 ? $leading : 16.0;
        $max = $this->contentWidth();
        foreach ($this->paragraphs($text) as $para) {
            if ($para === '') {
                $this->space(8);
                $this->indentNext = true;
                continue;
            }
            $indent = $this->indentNext;
            $this->indentNext = true;
            $x0 = $this->marginL + ($indent ? 16 : 0);
            $width = $max - ($indent ? 16 : 0);
            $lineIndex = 0;
            foreach ($this->wrap($para, $width) as $line) {
                $this->ensure($leading);
                $x = $lineIndex === 0 ? $x0 : $this->marginL;
                $this->buf .= $this->tj($x, $this->pdfY($this->y + $this->size), $line);
                $this->y += $leading;
                $lineIndex++;
                $width = $max;
                $x0 = $this->marginL;
            }
            $this->y += 3;
        }
    }

    public function muted(string $text): void
    {
        $this->setFont('I', 10);
        $this->fill(0.35, 0.35, 0.35);
        $this->indentNext = false;
        $this->write($text, 14);
        $this->fill(0, 0, 0);
        $this->setFont('', 11);
    }

    public function output(string $filename): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $out = $this->bytes();
        $name = str_replace(['"', "\r", "\n"], '', $filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . (string) strlen($out));
        echo $out;
        exit;
    }

    public function saveTo(string $path): void
    {
        file_put_contents($path, $this->bytes());
    }

    public function bytes(): string
    {
        $this->flushPage();
        if ($this->pages === []) {
            $this->pages[] = ['content' => '', 'right' => ''];
        }

        $nPages = count($this->pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $font1 = 3;
        $font2 = 4;
        $font3 = 5;
        $objs[$font1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>';
        $objs[$font2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>';
        $objs[$font3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>';

        $next = 7;
        $kids = [];
        $objs[6] = '<< /Producer (BookyChrome) /Creator (Booky) >>';
        foreach ($this->pages as $i => $page) {
            $chrome = $this->drawChrome ? $this->chrome($i + 1, (string) ($page['right'] ?? '')) : '';
            $content = $chrome . (string) ($page['content'] ?? '');
            $streamId = $next++;
            $pageId = $next++;
            $objs[$streamId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $objs[$pageId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_W,
                self::PAGE_H,
                $font1,
                $font2,
                $font3,
                $streamId
            );
            $kids[] = $pageId . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $nPages . ' >>';

        ksort($objs);
        $out = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $maxId = (int) max(array_keys($objs));
        $xref = strlen($out);
        $out .= "xref\n0 " . ($maxId + 1) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= 'trailer << /Size ' . ($maxId + 1) . " /Root 1 0 R /Info 6 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $out;
    }

    private function bottomLimit(): float
    {
        return self::PAGE_H - $this->marginB;
    }

    private function contentWidth(): float
    {
        return self::PAGE_W - $this->marginL - $this->marginR;
    }

    private function flushPage(): void
    {
        if ($this->buf === '') {
            return;
        }
        $this->pages[] = ['content' => $this->buf, 'right' => $this->headerRight];
        $this->buf = '';
    }

    private function chrome(int $page, string $right): string
    {
        $yHead = self::PAGE_H - 32;
        $yFoot = 28;
        $s = "%BookyChrome\n";
        $s .= 'q 0.35 0.35 0.35 rg ';
        $s .= $this->tjRaw($this->marginL, $yHead, $this->headerLeft, 'F3', 8);
        if ($right !== '') {
            $rw = $this->widthOf($right, 8, 'I');
            $s .= $this->tjRaw(self::PAGE_W - $this->marginR - $rw, $yHead, $right, 'F3', 8);
        }
        $s .= $this->tjRaw($this->marginL, $yFoot, $this->footerLeft, 'F3', 8);
        $num = (string) ($this->pageNumberStart + $page - 1);
        $nw = $this->widthOf($num, 8, '');
        $s .= $this->tjRaw(self::PAGE_W - $this->marginR - $nw, $yFoot, $num, 'F1', 8);
        $s .= " Q\n";
        return $s;
    }

    private function fill(float $r, float $g, float $b): void
    {
        $this->buf .= sprintf("%.2f %.2f %.2f rg\n", $r, $g, $b);
    }

    private function line(float $x, string $text): void
    {
        $this->buf .= $this->tj($x, $this->pdfY($this->y + $this->size), $text);
    }

    private function textCenter(string $text): void
    {
        $this->ensure($this->size * 1.4);
        $width = $this->widthOf($text);
        $x = $this->marginL + max(0, ($this->contentWidth() - $width) / 2);
        $this->buf .= $this->tj($x, $this->pdfY($this->y + $this->size), $text);
        $this->y += $this->size * 1.4;
    }

    private function writeCenteredWrapped(string $text, float $leading): void
    {
        foreach ($this->wrap($text, $this->contentWidth()) as $line) {
            $this->ensure($leading);
            $width = $this->widthOf($line);
            $x = $this->marginL + max(0, ($this->contentWidth() - $width) / 2);
            $this->buf .= $this->tj($x, $this->pdfY($this->y + $this->size), $line);
            $this->y += $leading;
        }
    }

    private function pdfY(float $fromTop): float
    {
        return self::PAGE_H - $fromTop;
    }

    private function fontRes(): string
    {
        return match ($this->style) {
            'B' => 'F2',
            'I' => 'F3',
            default => 'F1',
        };
    }

    private function tj(float $x, float $y, string $text, ?string $font = null, ?float $size = null): string
    {
        return $this->tjRaw($x, $y, $text, $font ?? $this->fontRes(), $size ?? $this->size);
    }

    private function tjRaw(float $x, float $y, string $text, string $font, float $size): string
    {
        $hex = bin2hex($this->toWin($text));
        return sprintf("BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm <%s> Tj ET\n", $font, $size, $x, $y, $hex);
    }

    private function toWin(string $utf8): string
    {
        $utf8 = strtr($utf8, [
            '…' => '...', '—' => '-', '–' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'",
            '«' => '"', '»' => '"', "\xc2\xa0" => ' ',
        ]);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $utf8);
        if ($out === false) {
            $out = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $utf8) ?? $utf8;
        }
        $out = str_replace(["\r", "\n", "\t"], ' ', $out);
        return $out;
    }

    private function clipToWidth(string $text, float $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($this->widthOf($text) <= $max) {
            return $text;
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cur = '';
        foreach ($chars as $ch) {
            $try = $cur . $ch;
            if ($this->widthOf($try . '…') > $max) {
                break;
            }
            $cur = $try;
        }
        return rtrim($cur) . '…';
    }

    private function clip(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $maxChars - 1, 'UTF-8')) . '…';
    }

    /** @return list<string> */
    private function paragraphs(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split("/\n{2,}/", trim($text)) ?: [];
        return $parts === [] ? [''] : $parts;
    }

    /** @return list<string> */
    private function wrap(string $text, float $max): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/ /u', $text) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            if ($this->widthOf($try) <= $max) {
                $cur = $try;
                continue;
            }
            if ($cur !== '') {
                $lines[] = $cur;
            }
            if ($this->widthOf($w) <= $max) {
                $cur = $w;
                continue;
            }
            foreach ($this->splitLong($w, $max) as $chunk) {
                $lines[] = $chunk;
            }
            $cur = '';
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines !== [] ? $lines : [''];
    }

    /** @return list<string> */
    private function splitLong(string $word, float $max): array
    {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $cur = '';
        foreach ($chars as $ch) {
            $try = $cur . $ch;
            if ($this->widthOf($try) <= $max) {
                $cur = $try;
            } else {
                if ($cur !== '') {
                    $out[] = $cur;
                }
                $cur = $ch;
            }
        }
        if ($cur !== '') {
            $out[] = $cur;
        }
        return $out;
    }

    private function widthOf(string $text, ?float $size = null, ?string $style = null): float
    {
        $size = $size ?? $this->size;
        $enc = $this->toWin($text);
        $w = 0;
        $len = strlen($enc);
        for ($i = 0; $i < $len; $i++) {
            $w += self::charW(ord($enc[$i]));
        }
        $bold = ($style ?? $this->style) === 'B' ? 1.04 : 1.0;
        return $w * $size * $bold / 1000;
    }

    private static function charW(int $c): int
    {
        static $map = null;
        if ($map === null) {
            $map = array_fill(0, 256, 500);
            $ascii = [
                32 => 250, 33 => 333, 34 => 408, 35 => 500, 36 => 500, 37 => 833, 38 => 778, 39 => 180,
                40 => 333, 41 => 333, 42 => 500, 43 => 564, 44 => 250, 45 => 333, 46 => 250, 47 => 278,
                48 => 500, 49 => 500, 50 => 500, 51 => 500, 52 => 500, 53 => 500, 54 => 500, 55 => 500,
                56 => 500, 57 => 500, 58 => 278, 59 => 278, 60 => 564, 61 => 564, 62 => 564, 63 => 444,
                64 => 921, 65 => 722, 66 => 667, 67 => 667, 68 => 722, 69 => 611, 70 => 556, 71 => 722,
                72 => 722, 73 => 333, 74 => 389, 75 => 722, 76 => 611, 77 => 889, 78 => 722, 79 => 722,
                80 => 556, 81 => 722, 82 => 667, 83 => 556, 84 => 611, 85 => 722, 86 => 722, 87 => 944,
                88 => 722, 89 => 722, 90 => 611, 91 => 333, 92 => 278, 93 => 333, 94 => 469, 95 => 500,
                96 => 333, 97 => 444, 98 => 500, 99 => 444, 100 => 500, 101 => 444, 102 => 333, 103 => 500,
                104 => 500, 105 => 278, 106 => 278, 107 => 500, 108 => 278, 109 => 778, 110 => 500, 111 => 500,
                112 => 500, 113 => 500, 114 => 333, 115 => 389, 116 => 278, 117 => 500, 118 => 500, 119 => 722,
                120 => 500, 121 => 500, 122 => 444, 123 => 480, 124 => 200, 125 => 480, 126 => 541,
            ];
            foreach ($ascii as $k => $v) {
                $map[$k] = $v;
            }
            $map[193] = 722;
            $map[201] = 611;
            $map[205] = 333;
            $map[209] = 722;
            $map[211] = 722;
            $map[218] = 722;
            $map[220] = 722;
            $map[225] = 444;
            $map[233] = 444;
            $map[237] = 278;
            $map[241] = 500;
            $map[243] = 500;
            $map[250] = 500;
            $map[252] = 500;
            $map[161] = 333;
            $map[191] = 444;
        }
        return $map[$c] ?? 500;
    }
}
