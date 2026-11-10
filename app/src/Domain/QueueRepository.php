<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class QueueRepository
{
    /** Spalten, die insert/update schreiben dürfen — nur diese Namen landen im SQL. */
    private const WRITABLE = [
        'title', 'brief', 'links', 'status', 'priority', 'depth', 'category_id',
        'claimed_by', 'claim_expires', 'post_id', 'result_note', 'created_by',
    ];

    /** Ein abgelaufener Claim zählt beim Lesen als offen; die Zeile selbst bleibt unverändert. */
    private const EFFECTIVE_STATUS = "CASE WHEN q.status = 'claimed' AND q.claim_expires <= UTC_TIMESTAMP() THEN 'open' ELSE q.status END";

    /** Dieselbe Bedingung für Lesen der Kandidaten und für den atomaren Claim. */
    private const CLAIMABLE = "(status = 'open' OR (status = 'claimed' AND claim_expires <= UTC_TIMESTAMP()))";

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $fields */
    public function insert(array $fields): int
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO queue_items (%s, created_at, updated_at) VALUES (%s, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        ));
        $statement->execute($this->encode($fields));

        return (int) $this->pdo->lastInsertId();
    }

    public function attachMedia(int $itemId, int $mediaId, ?string $note, bool $own): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO queue_media (queue_item_id, media_id, note, own_material) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$itemId, $mediaId, $note, $own ? 1 : 0]);
    }

    /** @return array<string, mixed>|null mit `effective_status` und `category` (Slug) */
    public function find(int $id): ?array
    {
        return $this->findOne($id, false);
    }

    /** Sperrt die Zeile bis zum Ende der Transaktion, damit Reservierung und Verknüpfung sich nicht überholen. */
    public function findForUpdate(int $id): ?array
    {
        return $this->findOne($id, true);
    }

    /** @return list<array{uid: string, kind: string, note: string|null, own_material: bool}> */
    public function media(int $itemId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.uid, m.kind, qm.note, qm.own_material FROM queue_media qm
             JOIN media m ON m.id = qm.media_id
             WHERE qm.queue_item_id = ? ORDER BY m.id'
        );
        $statement->execute([$itemId]);

        return array_map(
            static fn (array $row): array => [
                'uid' => (string) $row['uid'],
                'kind' => (string) $row['kind'],
                'note' => $row['note'],
                'own_material' => (int) $row['own_material'] === 1,
            ],
            $statement->fetchAll()
        );
    }

    /**
     * Filter auf den Status, wie ihn der Leser sieht (`effective_status`); wichtigste und älteste zuerst.
     *
     * @param list<string> $statuses leer = alle
     * @return list<array<string, mixed>>
     */
    public function list(array $statuses, int $limit, int $offset): array
    {
        $sql = $this->selectSql();
        if ($statuses !== []) {
            $sql .= ' WHERE ' . self::EFFECTIVE_STATUS . ' IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')';
        }
        $sql .= ' ORDER BY q.priority ASC, q.created_at ASC, q.id ASC LIMIT ? OFFSET ?';

        $statement = $this->pdo->prepare($sql);
        $position = 1;
        foreach ($statuses as $status) {
            $statement->bindValue($position++, $status);
        }
        $statement->bindValue($position++, max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue($position, max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    /** @return list<int> Reihenfolge der Vergabe: Priorität, Alter */
    public function claimCandidates(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM queue_items WHERE ' . self::CLAIMABLE . ' ORDER BY priority ASC, created_at ASC, id ASC LIMIT ?'
        );
        $statement->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Die Reservierung in einer Anweisung: Bedingung und Schreiben sind nicht zu trennen,
     * sonst reservierten zwei gleichzeitige Läufe denselben Auftrag.
     */
    public function tryClaim(int $id, string $actor, int $minutes): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE queue_items
             SET status = 'claimed', claimed_by = ?, claim_expires = UTC_TIMESTAMP() + INTERVAL ? MINUTE, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND " . self::CLAIMABLE
        );
        $statement->bindValue(1, $actor);
        $statement->bindValue(2, $minutes, PDO::PARAM_INT);
        $statement->bindValue(3, $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $assignments = array_map(static fn (string $column): string => $column . ' = ?', $columns);
        $statement = $this->pdo->prepare(
            'UPDATE queue_items SET ' . implode(', ', $assignments) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([...$this->encode($fields), $id]);
    }

    private function selectSql(): string
    {
        return 'SELECT q.*, ' . self::EFFECTIVE_STATUS . ' AS effective_status, c.slug AS category
                FROM queue_items q LEFT JOIN categories c ON c.id = q.category_id';
    }

    /** @return array<string, mixed>|null */
    private function findOne(int $id, bool $forUpdate): ?array
    {
        // FOR UPDATE sperrt nur die Zeile von queue_items; der Join auf categories bleibt ungesperrt.
        $statement = $this->pdo->prepare(
            $this->selectSql() . ' WHERE q.id = ?' . ($forUpdate ? ' FOR UPDATE OF q' : '')
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /** @param list<string|int> $columns */
    private function assertWritable(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, self::WRITABLE, true)) {
                throw new \InvalidArgumentException('Unbekannte Queue-Spalte: ' . $column);
            }
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<mixed>
     */
    private function encode(array $fields): array
    {
        if (array_key_exists('links', $fields)) {
            $fields['links'] = json_encode($fields['links'], self::JSON_FLAGS);
        }

        return array_values($fields);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['priority'] = (int) $row['priority'];
        $row['category_id'] = $row['category_id'] === null ? null : (int) $row['category_id'];
        $row['post_id'] = $row['post_id'] === null ? null : (int) $row['post_id'];
        $row['links'] = json_decode((string) $row['links'], true, 512, JSON_THROW_ON_ERROR);

        return $row;
    }
}
