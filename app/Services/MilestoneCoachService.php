<?php

declare(strict_types=1);

final class MilestoneCoachService
{
    /**
     * @param list<array<string,mixed>> $catalog
     * @return array{hero:array<string,mixed>,cards:list<array<string,mixed>>}
     */
    public static function build(array $catalog): array
    {
        $cards = [];
        foreach ($catalog as $row) {
            $cards[] = self::card($row);
        }

        usort($cards, static function (array $a, array $b): int {
            $order = ['stalled' => 0, 'starting' => 1, 'moving' => 2, 'almost' => 3, 'done' => 4];
            $oa = $order[$a['mood']] ?? 9;
            $ob = $order[$b['mood']] ?? 9;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return ((int) ($b['days_idle'] ?? 0)) <=> ((int) ($a['days_idle'] ?? 0));
        });

        return [
            'hero' => self::hero($catalog, $cards),
            'cards' => $cards,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function card(array $row): array
    {
        $p = $row['progress'] ?? [];
        $id = (int) ($row['id'] ?? 0);
        $title = (string) ($row['title'] ?? 'Este libro');
        $pct = (float) ($p['pct'] ?? 0);
        $pending = (int) ($p['chapters_pending'] ?? 0);
        $done = (int) ($p['chapters_done'] ?? 0);
        $total = (int) ($p['chapters_total'] ?? 0);
        $pages = (int) ($p['pages'] ?? 0);
        $avg = (float) ($p['avg_pages'] ?? 0);
        $last = (string) ($p['last_upload_at'] ?? '');
        $days = self::daysIdle($last);
        $next = self::nextStep($p, $id);

        $mood = 'moving';
        if ($pct >= 99.9 && $pending === 0 && !empty($p['has_outline']) && !empty($p['has_synopsis'])) {
            $mood = 'done';
        } elseif ($pct >= 80 || ($total > 0 && $pending <= 3 && $pending > 0)) {
            $mood = 'almost';
        } elseif ($done === 0 && $pages === 0) {
            $mood = 'starting';
        } elseif ($days >= 10 || ($days >= 5 && $pending > 0 && $done > 0)) {
            $mood = 'stalled';
        }

        return [
            'book_id' => $id,
            'title' => $title,
            'author' => (string) ($row['author_name'] ?? ''),
            'mood' => $mood,
            'pct' => $pct,
            'color' => (string) ($p['color'] ?? '#d97706'),
            'pages' => $pages,
            'pending' => $pending,
            'done' => $done,
            'total' => $total,
            'days_idle' => $days,
            'last_upload_at' => $last !== '' ? $last : null,
            'next' => $next,
            'headline' => self::headline($mood, $title, $pending, $days),
            'body' => self::body($mood, $title, $pct, $pending, $done, $total, $pages, $avg, $days, $next),
            'cta_href' => $next['href'],
            'cta_label' => $next['cta'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $catalog
     * @param list<array<string,mixed>> $cards
     * @return array<string,string>
     */
    private static function hero(array $catalog, array $cards): array
    {
        if ($catalog === []) {
            return [
                'kicker' => 'Empezar',
                'title' => 'Todavía no hay un libro en marcha, y eso también está bien.',
                'text' => 'Cuando cargues el primero, acá vas a ver qué falta y el próximo paso concreto. Un outline ya cuenta como empezar.',
            ];
        }

        $pages = 0;
        $pending = 0;
        $stalled = 0;
        foreach ($cards as $card) {
            $pages += (int) $card['pages'];
            $pending += (int) $card['pending'];
            if (($card['mood'] ?? '') === 'stalled') {
                $stalled++;
            }
        }

        if ($stalled > 0) {
            $one = null;
            foreach ($cards as $card) {
                if ($card['mood'] === 'stalled') {
                    $one = $card;
                    break;
                }
            }
            $name = $one['title'] ?? 'el libro';
            return [
                'kicker' => 'Seguimos',
                'title' => $name . ' no se cayó: solo está esperando el próximo archivo.',
                'text' => 'Ya hay ' . format_n($pages) . ' páginas escritas. No hace falta recuperar todo el ritmo hoy. Un capítulo alcanza para volver a empujar el porcentaje.',
            ];
        }

        if ($pending === 0) {
            return [
                'kicker' => 'Hecho',
                'title' => 'Los hitos están cubiertos. Eso es un libro sostenido hasta el final.',
                'text' => format_n($pages) . ' páginas en el tablero. Si querés pulir, pisá un capítulo. Si no, disfrutá el cierre.',
            ];
        }

        return [
            'kicker' => 'Podés terminarlo',
            'title' => 'Faltan ' . format_n($pending) . ' capítulos y el camino está a la vista.',
            'text' => 'Llevás ' . format_n($pages) . ' páginas. Cada archivo que subís suma solo. El próximo paso es uno, no los ' . format_n($pending) . ' juntos.',
        ];
    }

    /**
     * @param array<string,mixed> $p
     * @return array{label:string,href:string,cta:string}
     */
    private static function nextStep(array $p, int $bookId): array
    {
        $base = '/libros/' . $bookId;
        if (empty($p['has_outline'])) {
            return ['label' => 'Outline', 'href' => $base . '?focus=outline', 'cta' => 'Cargar outline'];
        }
        if (empty($p['has_synopsis'])) {
            return ['label' => 'Sinopsis', 'href' => $base . '?focus=sinopsis', 'cta' => 'Cargar sinopsis'];
        }
        foreach ($p['segments'] ?? [] as $seg) {
            if (empty($seg['done'])) {
                $name = (string) ($seg['label'] ?? 'el próximo capítulo');
                $cid = (int) ($seg['chapter_id'] ?? 0);
                $href = $cid > 0 ? $base . '?focus=capitulo-' . $cid : $base . '?focus=capitulos';
                return ['label' => $name, 'href' => $href, 'cta' => 'Subir ' . $name];
            }
        }
        return ['label' => 'Ficha', 'href' => $base, 'cta' => 'Abrir ficha'];
    }

    private static function daysIdle(?string $last): int
    {
        if ($last === null || trim($last) === '') {
            return 999;
        }
        $ts = strtotime($last);
        if ($ts === false) {
            return 999;
        }
        $days = (int) floor((time() - $ts) / 86400);
        return max(0, $days);
    }

    private static function headline(string $mood, string $title, int $pending, int $days): string
    {
        return match ($mood) {
            'done' => $title . ' está completo. Lo sostuviste.',
            'almost' => 'Estás a ' . $pending . ' capítulo' . ($pending === 1 ? '' : 's') . ' de cerrar ' . $title . '.',
            'stalled' => $title . ' puede volver hoy. Lleva ' . ($days >= 999 ? 'un rato' : $days . ' días') . ' sin un archivo nuevo.',
            'starting' => $title . ' ya tiene forma. El primer capítulo es el que enciende el porcentaje.',
            default => $title . ' se está armando. Faltan ' . $pending . ' y son alcanzables.',
        };
    }

    private static function body(
        string $mood,
        string $title,
        float $pct,
        int $pending,
        int $done,
        int $total,
        int $pages,
        float $avg,
        int $days,
        array $next
    ): string {
        $nextLabel = (string) ($next['label'] ?? 'el próximo paso');
        $pace = $avg > 0 ? 'Venís a ' . format_n($avg, 1) . ' páginas por capítulo cargado. ' : '';

        return match ($mood) {
            'done' => 'Outline, sinopsis y los ' . $total . ' capítulos están. ' . format_n($pages) . ' páginas sumadas. Si reescribís uno, el timestamp te va a decir cuándo lo pisaste.',
            'almost' => $pace . 'Llevás ' . format_pct($pct) . '. No es un sprint: es ' . $nextLabel . ' y después el que sigue. El libro ya es más hecho que pendiente.',
            'stalled' => 'No se perdió el trabajo: hay ' . $done . '/' . $total . ' capítulos y ' . format_n($pages) . ' páginas. Un hueco de ' . ($days >= 999 ? 'varios días' : $days . ' días') . ' es normal. Hoy alcanza con ' . $nextLabel . '. El resto espera sin reclamar.',
            'starting' => 'Los hitos de mapa (outline y sinopsis) importan, pero el libro cobra cuerpo con un capítulo. Subí ' . $nextLabel . ' y vas a ver páginas y porcentaje moverse juntos.',
            default => $pace . format_pct($pct) . ' del tablero. Faltan ' . $pending . ' capítulos. El siguiente concreto es ' . $nextLabel . '. Eso alcanza para decir que seguís escribiendo ' . $title . '.',
        };
    }
}
