<?php

declare(strict_types=1);

final class PdfMergeService
{
    /** @var array<string, string|null> */
    private static array $binCache = [];

    /**
     * @param list<string> $paths
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function merge(array $paths, string $outPath): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path) && filesize($path) > 0) {
                $files[] = $path;
            }
        }
        if ($files === []) {
            return ['ok' => false, 'error' => 'No hay PDF para empalmar.'];
        }
        if (count($files) === 1) {
            if (!@copy($files[0], $outPath)) {
                return ['ok' => false, 'error' => 'No se pudo copiar el PDF.'];
            }
            return ['ok' => true, 'path' => $outPath];
        }

        $dir = dirname($outPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return ['ok' => false, 'error' => 'PHP no puede escribir en storage/tmp. Dale permiso a www-data sobre esa carpeta.'];
        }

        $blocked = self::execBlockedMessage();
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        self::widenPath();

        $details = [];
        foreach ([
            fn () => self::cmdQpdf($files, $outPath),
            fn () => self::cmdGs($files, $outPath),
            fn () => self::cmdPdfunite($files, $outPath),
            fn () => self::cmdPdftk($files, $outPath),
        ] as $attempt) {
            @unlink($outPath);
            $err = $attempt();
            if ($err === null && is_file($outPath) && filesize($outPath) > 0) {
                return ['ok' => true, 'path' => $outPath];
            }
            if (is_string($err) && $err !== '') {
                $details[] = $err;
            }
        }

        return ['ok' => false, 'error' => self::mergeFailMessage($details)];
    }

    /**
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function overlay(string $base, string $stamp, string $outPath): array
    {
        if (!is_file($base) || filesize($base) <= 0) {
            return ['ok' => false, 'error' => 'No hay PDF base para sellar.'];
        }
        if (!is_file($stamp) || filesize($stamp) <= 0) {
            return ['ok' => false, 'error' => 'No hay sello de encabezado.'];
        }
        $dir = dirname($outPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $blocked = self::execBlockedMessage();
        if ($blocked !== null) {
            return ['ok' => false, 'error' => $blocked];
        }

        self::widenPath();

        $details = [];
        foreach ([
            fn () => self::underlayQpdf($base, $stamp, $outPath),
            fn () => self::overlayQpdf($base, $stamp, $outPath),
            fn () => self::overlayPdftk($base, $stamp, $outPath),
        ] as $attempt) {
            @unlink($outPath);
            $err = $attempt();
            if ($err === null && is_file($outPath) && filesize($outPath) > 0) {
                return ['ok' => true, 'path' => $outPath];
            }
            if (is_string($err) && $err !== '') {
                $details[] = $err;
            }
        }

        $hint = self::clipDetail(implode(' · ', $details));
        $msg = 'Se empalmaron los PDF, pero no se pudo sellar encabezado y pie. Hace falta qpdf o pdftk.';
        if ($hint !== '') {
            $msg .= ' ' . $hint;
        }
        return ['ok' => false, 'error' => $msg];
    }

    public static function hasChromeMarker(string $path): bool
    {
        return self::hasChrome($path);
    }

    /**
     * Reescribe el PDF a A4 simple (Ghostscript si está, si no qpdf) para que qpdf pueda sellar.
     *
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function flattenA4(string $inPath, string $outPath): array
    {
        if (!is_file($inPath) || filesize($inPath) <= 0) {
            return ['ok' => false, 'error' => 'PDF no encontrado.'];
        }
        self::widenPath();
        $dir = dirname($outPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $gs = self::findBin('gs') ?? self::findBin('gswin64c') ?? self::findBin('gswin32c');
        if ($gs !== null) {
            $cmd = escapeshellarg($gs) . ' -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite'
                . ' -dCompatibilityLevel=1.4 -dPDFSETTINGS=/prepress'
                . ' -dFIXEDMEDIA -dPDFFitPage=true'
                . ' -dDEVICEWIDTHPOINTS=595 -dDEVICEHEIGHTPOINTS=842'
                . ' -sOutputFile=' . escapeshellarg($outPath) . ' ' . escapeshellarg($inPath) . ' 2>&1';
            self::run($cmd);
            if (is_file($outPath) && filesize($outPath) > 0) {
                return ['ok' => true, 'path' => $outPath];
            }
        }

        return self::ensureA4($inPath, $outPath);
    }

    /**
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function overlayPageByPage(string $base, string $stamp, string $outPath, string $workDir): array
    {
        $pages = self::pageCount($base);
        if ($pages < 1) {
            return ['ok' => false, 'error' => 'El PDF del autor no tiene páginas.'];
        }
        @mkdir($workDir, 0777, true);
        $bin = self::findBin('qpdf');
        if ($bin === null) {
            return ['ok' => false, 'error' => 'No se encontró qpdf para sellar página por página.'];
        }

        $pieces = [];
        for ($i = 1; $i <= $pages; $i++) {
            $oneBase = $workDir . '/b-' . $i . '.pdf';
            $oneStamp = $workDir . '/s-' . $i . '.pdf';
            $oneOut = $workDir . '/o-' . $i . '.pdf';
            self::run(escapeshellarg($bin) . ' --empty --pages ' . escapeshellarg($base)
                . ' ' . $i . ' -- ' . escapeshellarg($oneBase) . ' 2>&1');
            self::run(escapeshellarg($bin) . ' --empty --pages ' . escapeshellarg($stamp)
                . ' ' . $i . ' -- ' . escapeshellarg($oneStamp) . ' 2>&1');
            if (!is_file($oneBase) || !is_file($oneStamp)) {
                return ['ok' => false, 'error' => 'No se pudo separar la página ' . $i . ' para sellar.'];
            }
            $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($oneStamp)
                . ' --underlay ' . escapeshellarg($oneBase)
                . ' -- ' . escapeshellarg($oneOut) . ' 2>&1';
            self::run($cmd);
            if (!is_file($oneOut) || filesize($oneOut) <= 0 || !self::hasChrome($oneOut)) {
                $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($oneBase)
                    . ' --overlay ' . escapeshellarg($oneStamp)
                    . ' -- ' . escapeshellarg($oneOut) . ' 2>&1';
                self::run($cmd);
            }
            if (!is_file($oneOut) || filesize($oneOut) <= 0 || !self::hasChrome($oneOut)) {
                return ['ok' => false, 'error' => 'No se pudo sellar la página ' . $i . ' del manuscrito.'];
            }
            $pieces[] = $oneOut;
        }

        $merged = self::merge($pieces, $outPath);
        if (!$merged['ok']) {
            return $merged;
        }
        if (!self::hasChrome($outPath)) {
            return ['ok' => false, 'error' => 'El sello se perdió al volver a unir las páginas.'];
        }
        return ['ok' => true, 'path' => $outPath];
    }

    public static function pageCount(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        self::widenPath();
        $qpdf = self::findBin('qpdf');
        if ($qpdf !== null) {
            $res = self::run(escapeshellarg($qpdf) . ' --show-npages ' . escapeshellarg($path) . ' 2>&1');
            $line = trim((string) ($res['lines'][0] ?? ''));
            if ($line !== '' && ctype_digit($line) && (int) $line > 0) {
                return (int) $line;
            }
        }
        $info = self::findBin('pdfinfo');
        if ($info !== null) {
            $res = self::run(escapeshellarg($info) . ' ' . escapeshellarg($path) . ' 2>&1');
            foreach ($res['lines'] as $line) {
                if (preg_match('/^Pages:\s+(\d+)/i', $line, $m)) {
                    return max(1, (int) $m[1]);
                }
            }
        }
        return max(1, PageCounter::count($path, basename($path)));
    }

    /**
     * @return array{ok:bool,error?:string,path?:string}
     */
    public static function ensureA4(string $inPath, string $outPath): array
    {
        if (!is_file($inPath) || filesize($inPath) <= 0) {
            return ['ok' => false, 'error' => 'PDF no encontrado.'];
        }

        self::widenPath();
        $dir = dirname($outPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $bin = self::findBin('qpdf');
        if ($bin !== null) {
            $cmd = escapeshellarg($bin) . ' --empty --pages ' . escapeshellarg($inPath)
                . ' 1-z --page-size=a4 -- ' . escapeshellarg($outPath) . ' 2>&1';
            self::run($cmd);
            if (is_file($outPath) && filesize($outPath) > 0) {
                return ['ok' => true, 'path' => $outPath];
            }
        }

        if (self::isA4($inPath)) {
            if (!@copy($inPath, $outPath)) {
                return ['ok' => false, 'error' => 'No se pudo copiar el PDF.'];
            }
            return ['ok' => true, 'path' => $outPath];
        }

        $gs = self::findBin('gs') ?? self::findBin('gswin64c') ?? self::findBin('gswin32c');
        if ($gs !== null) {
            $cmd = escapeshellarg($gs) . ' -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite'
                . ' -dPDFFitPage=true -dFIXEDMEDIA -dDEVICEWIDTHPOINTS=595 -dDEVICEHEIGHTPOINTS=842'
                . ' -sOutputFile=' . escapeshellarg($outPath) . ' ' . escapeshellarg($inPath) . ' 2>&1';
            self::run($cmd);
            if (is_file($outPath) && filesize($outPath) > 0) {
                return ['ok' => true, 'path' => $outPath];
            }
        }

        return ['ok' => false, 'error' => 'No se pudo convertir el PDF a A4. Exportá los capítulos en A4 desde Word/LibreOffice.'];
    }

    public static function isA4(string $path): bool
    {
        $size = self::pageDimensions($path);
        if ($size === null) {
            return true;
        }
        [$w, $h] = $size;
        $tol = 12.0;
        $a4w = 595.28;
        $a4h = 841.89;
        $matchPortrait = abs($w - $a4w) <= $tol && abs($h - $a4h) <= $tol;
        $matchLandscape = abs($w - $a4h) <= $tol && abs($h - $a4w) <= $tol;
        return $matchPortrait || $matchLandscape;
    }

    /** @return array{0:float,1:float}|null */
    public static function pageDimensions(string $path): ?array
    {
        $info = self::findBin('pdfinfo');
        if ($info === null) {
            return null;
        }
        self::widenPath();
        $res = self::run(escapeshellarg($info) . ' ' . escapeshellarg($path) . ' 2>&1');
        foreach ($res['lines'] as $line) {
            if (preg_match('/^Page size:\s+([\d.]+)\s+x\s+([\d.]+)/i', $line, $m)) {
                return [(float) $m[1], (float) $m[2]];
            }
        }
        return null;
    }

    /** @param list<string> $files */
    private static function cmdQpdf(array $files, string $out): ?string
    {
        $bin = self::findBin('qpdf');
        if ($bin === null) {
            return 'No se encontró el binario qpdf (¿está en /usr/bin/qpdf?).';
        }
        $cmd = escapeshellarg($bin) . ' --empty --pages ' . self::args($files) . ' -- ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0) {
            return null;
        }
        return 'qpdf: ' . self::clipDetail($res['text'] !== '' ? $res['text'] : ('código ' . $res['code']));
    }

    /** @param list<string> $files */
    private static function cmdGs(array $files, string $out): ?string
    {
        $bin = self::findBin('gs') ?? self::findBin('gswin64c') ?? self::findBin('gswin32c');
        if ($bin === null) {
            return '';
        }
        $cmd = escapeshellarg($bin) . ' -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($out)
            . ' ' . self::args($files) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0) {
            return null;
        }
        return 'gs: ' . self::clipDetail($res['text']);
    }

    /** @param list<string> $files */
    private static function cmdPdfunite(array $files, string $out): ?string
    {
        $bin = self::findBin('pdfunite');
        if ($bin === null) {
            return '';
        }
        $cmd = escapeshellarg($bin) . ' ' . self::args($files) . ' ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0) {
            return null;
        }
        return 'pdfunite: ' . self::clipDetail($res['text']);
    }

    /** @param list<string> $files */
    private static function cmdPdftk(array $files, string $out): ?string
    {
        $bin = self::findBin('pdftk');
        if ($bin === null) {
            return '';
        }
        $cmd = escapeshellarg($bin) . ' ' . self::args($files) . ' cat output ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0) {
            return null;
        }
        return 'pdftk: ' . self::clipDetail($res['text']);
    }

    /**
     * El sello es la página de Booky (A4 + encabezado). El manuscrito va DEBAJO.
     * Así el marco no depende de que qpdf logre dibujar un overlay sobre PDFs raros.
     */
    private static function underlayQpdf(string $base, string $stamp, string $out): ?string
    {
        $bin = self::findBin('qpdf');
        if ($bin === null) {
            return 'No se encontró qpdf para sellar.';
        }
        $cmd = escapeshellarg($bin) . ' --flatten-rotation ' . escapeshellarg($stamp)
            . ' --underlay ' . escapeshellarg($base)
            . ' --to=1-z --from=1-z -- ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0 && self::hasChrome($out)) {
            return null;
        }
        @unlink($out);
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($stamp)
            . ' --underlay ' . escapeshellarg($base)
            . ' --to=1-z --from=1-z -- ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0 && self::hasChrome($out)) {
            return null;
        }
        return 'qpdf underlay: ' . self::clipDetail($res['text'] !== '' ? $res['text'] : ('código ' . $res['code']));
    }

    private static function overlayQpdf(string $base, string $stamp, string $out): ?string
    {
        $bin = self::findBin('qpdf');
        if ($bin === null) {
            return 'No se encontró qpdf para sellar.';
        }

        $attempts = [
            escapeshellarg($bin) . ' --flatten-rotation ' . escapeshellarg($base)
                . ' --overlay ' . escapeshellarg($stamp)
                . ' --to=1-z --from=1-z -- ' . escapeshellarg($out) . ' 2>&1',
            escapeshellarg($bin) . ' ' . escapeshellarg($base) . ' --overlay ' . escapeshellarg($stamp)
                . ' --to=1-z --from=1 --repeat=1 -- ' . escapeshellarg($out) . ' 2>&1',
        ];
        foreach ($attempts as $cmd) {
            @unlink($out);
            $res = self::run($cmd);
            if (!is_file($out) || filesize($out) <= 0) {
                continue;
            }
            if (self::hasChrome($out)) {
                return null;
            }
            if ($res['code'] !== 0 && $res['text'] !== '') {
                return 'qpdf overlay: ' . self::clipDetail($res['text']);
            }
        }

        return 'qpdf overlay: el archivo salió sin el sello de Booky.';
    }

    private static function hasChrome(string $path): bool
    {
        $raw = (string) @file_get_contents($path);
        if ($raw === '') {
            return false;
        }
        return str_contains($raw, 'BookyChrome')
            || str_contains($raw, '/F3 8 Tf')
            || str_contains($raw, '/Times-Italic');
    }

    private static function overlayPdftk(string $base, string $stamp, string $out): ?string
    {
        $bin = self::findBin('pdftk');
        if ($bin === null) {
            return '';
        }
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($base) . ' stamp ' . escapeshellarg($stamp)
            . ' output ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0 && self::hasChrome($out)) {
            return null;
        }
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($base) . ' multistamp ' . escapeshellarg($stamp)
            . ' output ' . escapeshellarg($out) . ' 2>&1';
        $res = self::run($cmd);
        if (is_file($out) && filesize($out) > 0 && self::hasChrome($out)) {
            return null;
        }
        return 'pdftk stamp: ' . self::clipDetail($res['text']);
    }

    private static function findBin(string $name): ?string
    {
        if (array_key_exists($name, self::$binCache)) {
            return self::$binCache[$name];
        }

        $candidates = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/bin/' . $name,
            '/snap/bin/' . $name,
        ];
        foreach ($candidates as $path) {
            if (self::binWorks($path)) {
                return self::$binCache[$name] = $path;
            }
        }

        if (self::fnAllowed('exec')) {
            $out = [];
            $code = 1;
            @exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $code);
            $found = trim((string) ($out[0] ?? ''));
            if ($code === 0 && $found !== '' && self::binWorks($found)) {
                return self::$binCache[$name] = $found;
            }
        }

        return self::$binCache[$name] = null;
    }

    private static function binWorks(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        $res = self::run(escapeshellarg($path) . ' --version 2>&1');
        if ($res['code'] === 0) {
            return true;
        }
        // Algunos wrappers (pdftk) usan --help y salen ≠ 0 con --version.
        if (is_file($path)) {
            $help = self::run(escapeshellarg($path) . ' --help 2>&1');
            return $help['code'] === 0 || $help['text'] !== '';
        }
        return false;
    }

    /**
     * @return array{code:int,text:string,lines:list<string>}
     */
    private static function run(string $cmd): array
    {
        if (self::fnAllowed('exec')) {
            $lines = [];
            $code = 1;
            @exec($cmd, $lines, $code);
            $lines = array_values(array_map('strval', $lines));
            return ['code' => (int) $code, 'text' => trim(implode("\n", $lines)), 'lines' => $lines];
        }
        if (self::fnAllowed('proc_open')) {
            $spec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($cmd, $spec, $pipes, null, null);
            if (!is_resource($proc)) {
                return ['code' => 127, 'text' => '', 'lines' => []];
            }
            $stdout = (string) stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
            $text = trim($stdout . "\n" . $stderr);
            $lines = $text === '' ? [] : (preg_split("/\r\n|\n|\r/", $text) ?: []);
            return ['code' => (int) $code, 'text' => $text, 'lines' => array_values($lines)];
        }
        if (self::fnAllowed('shell_exec')) {
            $text = trim((string) @shell_exec($cmd));
            $lines = $text === '' ? [] : (preg_split("/\r\n|\n|\r/", $text) ?: []);
            return ['code' => $text === '' ? 1 : 0, 'text' => $text, 'lines' => array_values($lines)];
        }
        return ['code' => 127, 'text' => 'exec deshabilitado', 'lines' => []];
    }

    private static function fnAllowed(string $fn): bool
    {
        if (!function_exists($fn)) {
            return false;
        }
        $raw = (string) ini_get('disable_functions');
        if ($raw === '') {
            return true;
        }
        $disabled = array_map(static fn ($s) => strtolower(trim($s)), explode(',', $raw));
        return !in_array(strtolower($fn), $disabled, true);
    }

    private static function execBlockedMessage(): ?string
    {
        if (self::fnAllowed('exec') || self::fnAllowed('proc_open') || self::fnAllowed('shell_exec')) {
            return null;
        }
        return 'PHP tiene deshabilitado exec (disable_functions en php.ini). Apache no puede llamar a qpdf aunque esté instalado. Habilitá exec o proc_open y reiniciá Apache.';
    }

    private static function widenPath(): void
    {
        $now = (string) getenv('PATH');
        $extra = '/usr/bin:/usr/local/bin:/bin:/snap/bin';
        if ($now === '' || !str_contains($now, '/usr/bin')) {
            @putenv('PATH=' . $extra . ($now !== '' ? ':' . $now : ''));
        }
    }

    /** @param list<string> $files */
    private static function args(array $files): string
    {
        return implode(' ', array_map('escapeshellarg', $files));
    }

    /** @param list<string> $details */
    private static function mergeFailMessage(array $details): string
    {
        $details = array_values(array_filter($details, static fn ($s) => is_string($s) && trim($s) !== ''));
        $hint = self::clipDetail(implode(' · ', $details));
        if ($hint === '') {
            return 'No se pudo empalmar los PDF. En el servidor, como www-data: which qpdf && qpdf --version. Suele ser sudo apt install qpdf y comprobar que PHP pueda usar exec.';
        }
        return 'No se pudo empalmar los PDF. ' . $hint;
    }

    private static function clipDetail(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if (mb_strlen($text, 'UTF-8') <= 280) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, 277, 'UTF-8')) . '…';
    }
}
