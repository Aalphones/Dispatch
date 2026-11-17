<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

/**
 * Redaktionsquote (ADR-037): in jedem Fenster von `window` aufeinanderfolgenden Artikeln höchstens `capped_max` aus der
 * gedeckelten Hauptkategorie (Gaming) und mindestens `required_min` aus der Pflicht-Hauptkategorie (Geschichte).
 * Gerechnet wird auf der Hauptkategorie der primären Kategorie eines Artikels — die Hauptkategorie kommt aus `parent_id`.
 *
 * Die Regeln sind Vorzug, keine Sperre: Wo kein passender Artikel bereitsteht, erscheint trotzdem der nächste in der
 * gespeicherten Reihenfolge. Die Auswahl (`pick`, `arrange`) ist reine Rechnung auf Listen von Hauptkategorie-Slugs.
 */
final class QuotaService
{
    /** @var array{window: int, capped_main: string, capped_max: int, required_main: string, required_min: int} */
    private array $rules;

    /** SQL: Slug der Hauptkategorie der primären Kategorie des Artikels `$postIdColumn`. */
    private const MAIN_OF_POST = "(SELECT COALESCE(par.slug, c.slug) FROM post_categories pc
        JOIN categories c ON c.id = pc.category_id LEFT JOIN categories par ON par.id = c.parent_id
        WHERE pc.post_id = %s ORDER BY pc.is_primary DESC, c.id LIMIT 1)";

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, array $config)
    {
        $this->rules = $config['quota'];
    }

    /** @return array{window: int, capped_main: string, capped_max: int, required_main: string, required_min: int} */
    public function rules(): array
    {
        return $this->rules;
    }

    /** SQL-Ausdruck für den Slug der Hauptkategorie eines Artikels, z. B. `QuotaService::mainOfPostSql('p.id')`. */
    public static function mainOfPostSql(string $postIdColumn): string
    {
        return sprintf(self::MAIN_OF_POST, $postIdColumn);
    }

    /**
     * Was braucht der nächste Artikel, wenn davor `$recent` (ältester zuerst) erschienen ist?
     *
     * @param list<string|null> $recent Hauptkategorien
     * @return array{gaming_allowed: bool, history_needed: bool}
     */
    public function needs(array $recent): array
    {
        $tail = array_slice($recent, -($this->rules['window'] - 1));
        $capped = count(array_filter($tail, fn (?string $main): bool => $main === $this->rules['capped_main']));
        $required = count(array_filter($tail, fn (?string $main): bool => $main === $this->rules['required_main']));

        return [
            'gaming_allowed' => $capped + 1 <= $this->rules['capped_max'],
            // Ein neuer Nicht-Geschichtsartikel würde das Fenster ohne Geschichte lassen — nur sinnvoll, wenn das Fenster voll ist.
            'history_needed' => count($tail) === $this->rules['window'] - 1 && $required < $this->rules['required_min'],
        ];
    }

    /**
     * Index des Kandidaten, der als Nächstes dran ist: der erste erlaubte, bei Geschichtsbedarf der erste erlaubte
     * Geschichtsartikel; gibt es keinen erlaubten, der erste überhaupt (nie blockieren).
     *
     * @param list<string|null> $mains Hauptkategorie je Kandidat, in gespeicherter Reihenfolge
     * @param list<string|null> $recent
     */
    public function pick(array $mains, array $recent): int
    {
        if ($mains === []) {
            return -1;
        }

        $needs = $this->needs($recent);
        $allowed = [];
        foreach ($mains as $index => $main) {
            if ($main === $this->rules['capped_main'] && !$needs['gaming_allowed']) {
                continue;
            }
            $allowed[] = $index;
        }
        if ($allowed === []) {
            return 0;
        }
        if ($needs['history_needed']) {
            foreach ($allowed as $index) {
                if ($mains[$index] === $this->rules['required_main']) {
                    return $index;
                }
            }
        }

        return $allowed[0];
    }

    /**
     * Reihenfolge, in der die Kandidaten erscheinen, wenn jeder Termin mit `pick` besetzt wird.
     *
     * @param list<string|null> $mains in gespeicherter Reihenfolge
     * @param list<string|null> $recent
     * @return list<int> Indizes aus `$mains` in Erscheinungsreihenfolge
     */
    public function arrange(array $mains, array $recent): array
    {
        $remaining = array_keys($mains);
        $order = [];
        while ($remaining !== []) {
            $chosen = $this->pick(array_map(static fn (int $index): ?string => $mains[$index], $remaining), $recent);
            $index = $remaining[$chosen];
            $order[] = $index;
            $recent[] = $mains[$index];
            array_splice($remaining, $chosen, 1);
        }

        return $order;
    }

    /**
     * Fenster, die die Regeln verletzen (nur volle Fenster).
     *
     * @param list<string|null> $sequence Hauptkategorien in Erscheinungsreihenfolge
     * @return list<array{from: int, to: int, gaming: int, history: int, reason: string}> Positionen ab 1 in `$sequence`
     */
    public function violations(array $sequence): array
    {
        $window = $this->rules['window'];
        $found = [];
        for ($start = 0; $start + $window <= count($sequence); $start++) {
            $slice = array_slice($sequence, $start, $window);
            $gaming = count(array_filter($slice, fn (?string $main): bool => $main === $this->rules['capped_main']));
            $history = count(array_filter($slice, fn (?string $main): bool => $main === $this->rules['required_main']));
            $reasons = [];
            if ($gaming > $this->rules['capped_max']) {
                $reasons[] = 'zu viel Gaming';
            }
            if ($history < $this->rules['required_min']) {
                $reasons[] = 'keine Geschichte';
            }
            if ($reasons !== []) {
                $found[] = ['from' => $start + 1, 'to' => $start + $window, 'gaming' => $gaming, 'history' => $history, 'reason' => implode(', ', $reasons)];
            }
        }

        return $found;
    }

    /**
     * Hauptkategorien der zuletzt erschienenen Artikel, ältester zuerst.
     *
     * @return list<string|null>
     */
    public function recentMains(?int $limit = null): array
    {
        $limit ??= $this->rules['window'] - 1;
        $statement = $this->pdo->prepare(
            'SELECT ' . self::mainOfPostSql('p.id') . " AS main FROM posts p
             WHERE p.published_revision IS NOT NULL AND p.status <> 'archived' AND p.published_at IS NOT NULL
             ORDER BY p.published_at DESC, p.id DESC LIMIT ?"
        );
        $statement->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_reverse(array_map(static fn ($main): ?string => $main === null ? null : (string) $main, $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * Hauptkategorie je Queue-Auftrag; ohne Kategorie `null`.
     *
     * @param list<int> $queueIds
     * @return array<int, string|null>
     */
    public function mainsOfQueue(array $queueIds): array
    {
        if ($queueIds === []) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT q.id, COALESCE(par.slug, c.slug) AS main FROM queue_items q
             LEFT JOIN categories c ON c.id = q.category_id LEFT JOIN categories par ON par.id = c.parent_id
             WHERE q.id IN (' . implode(', ', array_fill(0, count($queueIds), '?')) . ')'
        );
        $statement->execute($queueIds);

        $mains = [];
        foreach ($statement->fetchAll() as $row) {
            $mains[(int) $row['id']] = $row['main'] === null ? null : (string) $row['main'];
        }

        return $mains;
    }

    /**
     * Die Queue-Aufträge so geordnet, dass der nächste zu schreibende vorn steht: Er passt an das Ende der
     * Pipeline (erschienen + eingeplant in Erscheinungsreihenfolge). Der Rest bleibt in gespeicherter Reihenfolge.
     *
     * @param list<int> $queueIds in gespeicherter Reihenfolge
     * @return list<int>
     */
    public function preferQueueOrder(array $queueIds): array
    {
        if ($queueIds === []) {
            return [];
        }

        $mainsById = $this->mainsOfQueue($queueIds);
        $mains = array_map(static fn (int $id): ?string => $mainsById[$id] ?? null, $queueIds);
        $chosen = $this->pick($mains, $this->pipeline());
        $first = $queueIds[$chosen];

        return [$first, ...array_values(array_filter($queueIds, static fn (int $id): bool => $id !== $first))];
    }

    /**
     * Erschienene und eingeplante Artikel in Erscheinungsreihenfolge (Hauptkategorien); die Warteschlange mit Vorzug.
     *
     * @return list<string|null>
     */
    public function pipeline(): array
    {
        $recent = $this->recentMains();
        $waiting = $this->waitingMains();

        $order = $this->arrange($waiting, $recent);

        return [...$recent, ...array_map(static fn (int $index): ?string => $waiting[$index], $order)];
    }

    /** @return list<string|null> Hauptkategorien der eingeplanten Warteschlangen-Artikel in gespeicherter Reihenfolge */
    private function waitingMains(): array
    {
        $statement = $this->pdo->query(
            'SELECT ' . self::mainOfPostSql('p.id') . " AS main FROM posts p
             WHERE p.status = 'scheduled' AND p.schedule_mode = 'slot' ORDER BY p.schedule_order, p.id"
        );

        return array_map(static fn ($main): ?string => $main === null ? null : (string) $main, $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Zustand für das Briefing: Regeln, was der nächste Artikel braucht, die absehbare Reihenfolge und Verstöße.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $pipeline = $this->pipeline();
        $needs = $this->needs($pipeline);
        $recent = $this->recentMains();

        return [
            'rule' => sprintf(
                'In jedem Fenster von %d Veröffentlichungen höchstens %d aus „%s“ und mindestens %d aus „%s“ (Hauptkategorie der primären Kategorie).',
                $this->rules['window'], $this->rules['capped_max'], $this->rules['capped_main'], $this->rules['required_min'], $this->rules['required_main']
            ),
            'recent_published' => $recent,
            'upcoming' => array_slice($pipeline, count($recent)),
            'next_gaming_allowed' => $needs['gaming_allowed'],
            'next_needs_history' => $needs['history_needed'],
            'violations' => $this->violations($pipeline),
        ];
    }
}
