<?php

declare(strict_types=1);

final class BookService
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT b.*, a.name AS author_name
             FROM books b INNER JOIN authors a ON a.id = b.author_id
             WHERE b.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(?int $authorId = null): array
    {
        $ids = AccessService::allowedBookIds();
        $sql = 'SELECT b.*, a.name AS author_name
                FROM books b INNER JOIN authors a ON a.id = b.author_id';
        $where = [];
        $params = [];
        if (!Auth::isAdmin()) {
            if ($ids === []) {
                return [];
            }
            $where[] = 'b.id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        if ($authorId) {
            $where[] = 'b.author_id = :aid';
            $params['aid'] = $authorId;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.name, b.title';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string,mixed> $structure
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(array $data, array $structure): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $authorId = (int) ($data['author_id'] ?? 0);
        if ($title === '' || $authorId <= 0) {
            return ['ok' => false, 'error' => 'Autor y título son obligatorios.'];
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO books (author_id, title, subtitle, description, genre, language, isbn, target_pages, status, updated_at)
             VALUES (:author_id, :title, :subtitle, :description, :genre, :language, :isbn, :target_pages, :status, NOW())'
        )->execute(self::bind($data, $authorId, $title));
        $id = (int) $pdo->lastInsertId();
        self::syncStructure($id, $structure);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param array<string,mixed> $structure
     * @return array{ok:bool,error?:string}
     */
    public static function update(int $id, array $data, array $structure): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $authorId = (int) ($data['author_id'] ?? 0);
        if ($title === '' || $authorId <= 0) {
            return ['ok' => false, 'error' => 'Autor y título son obligatorios.'];
        }
        $bind = self::bind($data, $authorId, $title);
        $bind['id'] = $id;
        Database::pdo()->prepare(
            'UPDATE books
             SET author_id = :author_id, title = :title, subtitle = :subtitle, description = :description,
                 genre = :genre, language = :language, isbn = :isbn, target_pages = :target_pages,
                 status = :status, updated_at = NOW()
             WHERE id = :id'
        )->execute($bind);
        self::syncStructure($id, $structure);
        return ['ok' => true];
    }

    public static function delete(int $id): void
    {
        foreach (DocumentService::forBook($id) as $doc) {
            DocumentService::delete((int) $doc['id']);
        }
        Database::pdo()->prepare('DELETE FROM books WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public static function chapters(int $bookId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*, p.title AS part_title,
                    d.id AS document_id, d.page_count, d.original_name, d.created_at AS uploaded_at,
                    pdf.id AS pdf_id, pdf.page_count AS pdf_page_count, pdf.original_name AS pdf_name,
                    pdf.created_at AS pdf_uploaded_at, pdf.file_path AS pdf_path
             FROM chapters c
             LEFT JOIN book_parts p ON p.id = c.part_id
             LEFT JOIN documents d ON d.chapter_id = c.id AND d.kind = \'chapter\'
             LEFT JOIN documents pdf ON pdf.chapter_id = c.id AND pdf.kind = \'pdf\'
             WHERE c.book_id = :id
             ORDER BY c.sort_order, c.id'
        );
        $stmt->execute(['id' => $bookId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string,mixed>> */
    public static function parts(int $bookId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM book_parts WHERE book_id = :id ORDER BY sort_order, id'
        );
        $stmt->execute(['id' => $bookId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array{intro:?array<string,mixed>,epilogue:?array<string,mixed>,parts:list<array<string,mixed>>,chapters:list<array<string,mixed>>}
     */
    public static function formStructure(?int $bookId): array
    {
        $empty = [
            'intro' => null,
            'epilogue' => null,
            'parts' => [],
            'chapters' => [['id' => '', 'title' => '', 'part_id' => '']],
        ];
        if (!$bookId) {
            return $empty;
        }

        $intro = null;
        $epilogue = null;
        $chapters = [];
        foreach (self::chapters($bookId) as $ch) {
            $kind = (string) ($ch['kind'] ?? 'chapter');
            if ($kind === 'introduction') {
                $intro = $ch;
                continue;
            }
            if ($kind === 'epilogue') {
                $epilogue = $ch;
                continue;
            }
            $chapters[] = $ch;
        }

        $parts = [];
        foreach (self::parts($bookId) as $part) {
            $parts[] = [
                'id' => (int) $part['id'],
                'title' => (string) $part['title'],
            ];
        }

        if ($chapters === []) {
            $chapters = [['id' => '', 'title' => '', 'part_id' => '']];
        }

        return [
            'intro' => $intro,
            'epilogue' => $epilogue,
            'parts' => $parts,
            'chapters' => $chapters,
        ];
    }

    /**
     * @return list<array{heading:string,kind:string,items:list<array<string,mixed>>}>
     */
    public static function groupedSections(int $bookId): array
    {
        $parts = self::parts($bookId);
        $intro = [];
        $epilogue = [];
        $loose = [];
        $byPart = [];
        foreach (self::chapters($bookId) as $ch) {
            $kind = (string) ($ch['kind'] ?? 'chapter');
            if ($kind === 'introduction') {
                $intro[] = $ch;
            } elseif ($kind === 'epilogue') {
                $epilogue[] = $ch;
            } elseif ((int) ($ch['part_id'] ?? 0) > 0) {
                $byPart[(int) $ch['part_id']][] = $ch;
            } else {
                $loose[] = $ch;
            }
        }

        $out = [];
        if ($intro !== []) {
            $out[] = ['heading' => 'Introducción', 'kind' => 'introduction', 'items' => $intro];
        }
        foreach ($parts as $part) {
            $items = $byPart[(int) $part['id']] ?? [];
            if ($items !== []) {
                $out[] = ['heading' => (string) $part['title'], 'kind' => 'part', 'items' => $items];
            }
        }
        if ($loose !== []) {
            $out[] = [
                'heading' => $parts !== [] ? 'Sin parte' : 'Capítulos',
                'kind' => 'loose',
                'items' => $loose,
            ];
        }
        if ($epilogue !== []) {
            $out[] = ['heading' => 'Epílogo', 'kind' => 'epilogue', 'items' => $epilogue];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $structure
     */
    public static function syncStructure(int $bookId, array $structure): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $keepParts = [];
            $keepChapters = [];
            $order = 1;

            $updPart = $pdo->prepare(
                'UPDATE book_parts SET title = :title, sort_order = :ord WHERE id = :id AND book_id = :bid'
            );
            $insPart = $pdo->prepare(
                'INSERT INTO book_parts (book_id, sort_order, title) VALUES (:bid, :ord, :title)'
            );
            $updCh = $pdo->prepare(
                'UPDATE chapters
                 SET title = :title, sort_order = :ord, kind = :kind, part_id = NULLIF(:part_id, 0)
                 WHERE id = :id AND book_id = :bid'
            );
            $insCh = $pdo->prepare(
                'INSERT INTO chapters (book_id, part_id, sort_order, title, kind)
                 VALUES (:bid, NULLIF(:part_id, 0), :ord, :title, :kind)'
            );

            $saveChapter = static function (
                string $kind,
                string $title,
                int $cid,
                ?int $partId
            ) use ($pdo, $updCh, $insCh, $bookId, &$order, &$keepChapters): void {
                $title = trim($title);
                if ($title === '') {
                    return;
                }
                if ($cid > 0) {
                    $updCh->execute([
                        'title' => $title,
                        'ord' => $order,
                        'kind' => $kind,
                        'part_id' => $partId ?? 0,
                        'id' => $cid,
                        'bid' => $bookId,
                    ]);
                    $keepChapters[] = $cid;
                } else {
                    $insCh->execute([
                        'bid' => $bookId,
                        'part_id' => $partId ?? 0,
                        'ord' => $order,
                        'title' => $title,
                        'kind' => $kind,
                    ]);
                    $keepChapters[] = (int) $pdo->lastInsertId();
                }
                $order++;
            };

            $intro = $structure['intro'] ?? null;
            if (is_array($intro)) {
                $saveChapter(
                    'introduction',
                    (string) ($intro['title'] ?? 'Introducción'),
                    (int) ($intro['id'] ?? 0),
                    null
                );
            }

            $partOrder = 1;
            $partIds = [];
            foreach ($structure['parts'] ?? [] as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $ptitle = trim((string) ($part['title'] ?? ''));
                if ($ptitle === '') {
                    $ptitle = 'Parte ' . format_roman($partOrder);
                }
                $pid = (int) ($part['id'] ?? 0);
                if ($pid > 0) {
                    $updPart->execute([
                        'title' => $ptitle,
                        'ord' => $partOrder,
                        'id' => $pid,
                        'bid' => $bookId,
                    ]);
                } else {
                    $insPart->execute(['bid' => $bookId, 'ord' => $partOrder, 'title' => $ptitle]);
                    $pid = (int) $pdo->lastInsertId();
                }
                $keepParts[] = $pid;
                $partIds[] = $pid;
                $partOrder++;
            }

            foreach ($structure['chapters'] ?? [] as $ch) {
                if (!is_array($ch)) {
                    continue;
                }
                $idx = (int) ($ch['part_index'] ?? 0);
                $partId = ($idx > 0 && isset($partIds[$idx - 1])) ? $partIds[$idx - 1] : null;
                $saveChapter('chapter', (string) ($ch['title'] ?? ''), (int) ($ch['id'] ?? 0), $partId);
            }

            $epilogue = $structure['epilogue'] ?? null;
            if (is_array($epilogue)) {
                $saveChapter(
                    'epilogue',
                    (string) ($epilogue['title'] ?? 'Epílogo'),
                    (int) ($epilogue['id'] ?? 0),
                    null
                );
            }

            $existingCh = $pdo->prepare('SELECT id FROM chapters WHERE book_id = :id');
            $existingCh->execute(['id' => $bookId]);
            $delCh = $pdo->prepare('DELETE FROM chapters WHERE id = :id AND book_id = :bid');
            foreach (array_map('intval', $existingCh->fetchAll(PDO::FETCH_COLUMN) ?: []) as $cid) {
                if (!in_array($cid, $keepChapters, true)) {
                    $delCh->execute(['id' => $cid, 'bid' => $bookId]);
                }
            }

            $existingParts = $pdo->prepare('SELECT id FROM book_parts WHERE book_id = :id');
            $existingParts->execute(['id' => $bookId]);
            $delPart = $pdo->prepare('DELETE FROM book_parts WHERE id = :id AND book_id = :bid');
            foreach (array_map('intval', $existingParts->fetchAll(PDO::FETCH_COLUMN) ?: []) as $pid) {
                if (!in_array($pid, $keepParts, true)) {
                    $delPart->execute(['id' => $pid, 'bid' => $bookId]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function bind(array $data, int $authorId, string $title): array
    {
        return [
            'author_id' => $authorId,
            'title' => $title,
            'subtitle' => trim((string) ($data['subtitle'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'genre' => trim((string) ($data['genre'] ?? '')) ?: null,
            'language' => trim((string) ($data['language'] ?? 'es')) ?: 'es',
            'isbn' => trim((string) ($data['isbn'] ?? '')) ?: null,
            'target_pages' => null,
            'status' => trim((string) ($data['status'] ?? 'en_proceso')) ?: 'en_proceso',
        ];
    }
}
