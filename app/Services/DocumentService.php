<?php

declare(strict_types=1);

final class DocumentService
{
    public const KIND_OUTLINE = 'outline';
    public const KIND_SYNOPSIS = 'synopsis';
    public const KIND_CHAPTER = 'chapter';
    public const KIND_PDF = 'pdf';

    /** @return list<array<string,mixed>> */
    public static function forBook(int $bookId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM documents WHERE book_id = :id ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['id' => $bookId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function milestone(int $bookId, string $kind): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM documents WHERE book_id = :id AND kind = :k ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $bookId, 'k' => $kind]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function forChapter(int $chapterId): ?array
    {
        return self::forChapterKind($chapterId, self::KIND_CHAPTER);
    }

    public static function pdfForChapter(int $chapterId): ?array
    {
        return self::forChapterKind($chapterId, self::KIND_PDF);
    }

    public static function forChapterKind(int $chapterId, string $kind): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM documents WHERE chapter_id = :id AND kind = :k ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $chapterId, 'k' => $kind]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function storeMilestone(int $bookId, string $kind, array $file, ?int $userId): array
    {
        if (!in_array($kind, [self::KIND_OUTLINE, self::KIND_SYNOPSIS], true)) {
            return ['ok' => false, 'error' => 'Tipo de documento inválido.'];
        }
        foreach (self::forBook($bookId) as $old) {
            if (($old['kind'] ?? '') === $kind) {
                self::delete((int) $old['id']);
            }
        }
        $title = $kind === self::KIND_OUTLINE ? 'Outline' : 'Sinopsis';
        return self::store($bookId, null, $kind, $title, $file, $userId);
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function storeChapter(int $bookId, int $chapterId, array $file, ?int $userId, string $chapterTitle): array
    {
        $old = self::forChapter($chapterId);
        if ($old) {
            self::delete((int) $old['id']);
        }
        return self::store($bookId, $chapterId, self::KIND_CHAPTER, $chapterTitle, $file, $userId);
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function storePdf(int $bookId, int $chapterId, array $file, ?int $userId, string $chapterTitle): array
    {
        $old = self::pdfForChapter($chapterId);
        if ($old) {
            self::delete((int) $old['id']);
        }
        return self::store($bookId, $chapterId, self::KIND_PDF, $chapterTitle . ' (PDF)', $file, $userId);
    }

    public static function delete(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            return;
        }
        $abs = self::absolutePath($row);
        if (is_file($abs)) {
            @unlink($abs);
        }
        Database::pdo()->prepare('DELETE FROM documents WHERE id = :id')->execute(['id' => $id]);
    }

    public static function absolutePath(array $doc): string
    {
        $rel = (string) ($doc['file_path'] ?? '');
        return self::appRoot() . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    }

    private static function appRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return string|null error */
    private static function ensureDir(string $abs): ?string
    {
        if (is_dir($abs)) {
            if (!is_writable($abs)) {
                @chmod($abs, 0777);
            }
            return is_writable($abs) ? null : 'Sin permiso de escritura en storage/uploads (www-data).';
        }
        $parent = dirname($abs);
        if ($parent !== $abs && !is_dir($parent)) {
            $err = self::ensureDir($parent);
            if ($err !== null) {
                return $err;
            }
        }
        if (!is_dir($parent)) {
            return 'No existe el directorio padre de archivos.';
        }
        $prev = umask(0);
        $ok = @mkdir($abs, 0777, true);
        umask($prev);
        if (!$ok && !is_dir($abs)) {
            return 'No se pudo crear el directorio de archivos.';
        }
        @chmod($abs, 0777);
        return is_writable($abs) ? null : 'Directorio creado pero sin permiso de escritura.';
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string,id?:int}
     */
    private static function store(int $bookId, ?int $chapterId, string $kind, string $title, array $file, ?int $userId): array
    {
        $check = self::validate($file, $kind);
        if (!$check['ok']) {
            return $check;
        }

        $dirRel = 'storage/uploads/' . $bookId;
        $dirAbs = self::appRoot() . '/' . $dirRel;
        $dirErr = self::ensureDir($dirAbs);
        if ($dirErr !== null) {
            return ['ok' => false, 'error' => $dirErr];
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $safe = bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
        $relPath = $dirRel . '/' . $safe;
        $absPath = $dirAbs . '/' . $safe;

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'Archivo de subida inválido.'];
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $absPath)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo en disco.'];
        }
        @chmod($absPath, 0664);

        $pages = PageCounter::count($absPath, (string) ($file['name'] ?? ''));
        $words = $kind === self::KIND_PDF
            ? 0
            : DocumentText::wordCountFromFile($absPath, (string) ($file['name'] ?? ''));
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO documents (book_id, chapter_id, kind, title, original_name, file_path, mime_type, page_count, word_count, uploaded_by)
             VALUES (:book_id, :chapter_id, :kind, :title, :original_name, :file_path, :mime, :pages, :words, :uid)'
        )->execute([
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'kind' => $kind,
            'title' => mb_substr($title, 0, 180),
            'original_name' => mb_substr((string) ($file['name'] ?? 'archivo'), 0, 220),
            'file_path' => $relPath,
            'mime' => mb_substr((string) ($file['type'] ?? 'application/octet-stream'), 0, 120),
            'pages' => $pages,
            'words' => $words,
            'uid' => $userId,
        ]);
        return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    /**
     * @param array<string,mixed> $file
     * @return array{ok:bool,error?:string}
     */
    private static function validate(array $file, string $kind = self::KIND_CHAPTER): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Elegí un archivo.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Error al subir el archivo.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 40 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'El archivo debe pesar entre 1 byte y 40 MB.'];
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($kind === self::KIND_PDF) {
            if ($ext !== 'pdf') {
                return ['ok' => false, 'error' => 'En este espacio solo va un PDF.'];
            }
            return ['ok' => true];
        }
        if (!in_array($ext, ['ods', 'odt', 'docx'], true)) {
            return ['ok' => false, 'error' => 'En este espacio usá ODS, ODT o DOCX.'];
        }
        return ['ok' => true];
    }
}
