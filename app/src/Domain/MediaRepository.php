<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Like;
use PDO;

final class MediaRepository
{
    /** Spalten, die insert/updateMeta schreiben dürfen — nur diese Namen landen im SQL. */
    private const INSERT_COLUMNS = [
        'uid', 'kind', 'mime', 'ext', 'bytes', 'sha256', 'width', 'height', 'variants',
        'alt', 'caption', 'credit', 'license', 'license_url', 'source_url', 'origin',
    ];

    private const META_COLUMNS = ['alt', 'caption', 'credit', 'license', 'license_url', 'source_url'];

    private const SEARCH_COLUMNS = ['uid', 'alt', 'caption', 'credit'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByUid(string $uid): ?array
    {
        return $this->findOne('uid', $uid);
    }

    /** @return array<string, mixed>|null */
    public function findBySha(string $sha): ?array
    {
        return $this->findOne('sha256', $sha);
    }

    /**
     * @param list<string> $uids
     * @return array<string, array<string, mixed>> Schlüssel = uid
     */
    public function findManyByUid(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM media WHERE uid IN (' . implode(', ', array_fill(0, count($uids), '?')) . ')'
        );
        $statement->execute(array_values($uids));

        $found = [];
        foreach ($statement->fetchAll() as $row) {
            $found[(string) $row['uid']] = $this->decode($row);
        }

        return $found;
    }

    /** @param array<string, mixed> $fields vollständig: alle Spalten aus INSERT_COLUMNS */
    public function insert(array $fields): int
    {
        $columns = array_keys($fields);
        foreach ($columns as $column) {
            if (!in_array($column, self::INSERT_COLUMNS, true)) {
                throw new \InvalidArgumentException('Unbekannte Medien-Spalte: ' . $column);
            }
        }
        $fields['variants'] = json_encode($fields['variants'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO media (%s, created_at, updated_at) VALUES (%s, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        ));
        $statement->execute(array_values($fields));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, string|null> $fields nur Metadaten-Spalten */
    public function updateMeta(string $uid, array $fields): void
    {
        $columns = array_keys($fields);
        foreach ($columns as $column) {
            if (!in_array($column, self::META_COLUMNS, true)) {
                throw new \InvalidArgumentException('Keine Metadaten-Spalte: ' . $column);
            }
        }
        if ($columns === []) {
            return;
        }

        $assignments = array_map(static fn (string $column): string => $column . ' = ?', $columns);
        $statement = $this->pdo->prepare(
            'UPDATE media SET ' . implode(', ', $assignments) . ', updated_at = UTC_TIMESTAMP() WHERE uid = ?'
        );
        $statement->execute([...array_values($fields), $uid]);
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $kind, int $limit, int $offset, ?string $query = null): array
    {
        $conditions = [];
        $parameters = [];
        if ($kind !== null) {
            $conditions[] = 'kind = ?';
            $parameters[] = $kind;
        }
        if ($query !== null) {
            $searchConditions = [];
            foreach (self::SEARCH_COLUMNS as $column) {
                $searchConditions[] = $column . ' LIKE ? ' . Like::ESCAPE_CLAUSE;
                $parameters[] = Like::contains($query);
            }
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        $sql = 'SELECT * FROM media' . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
    }

    /** Öffentlich ist ein Medium, sobald ein Live-Stand es nutzt; die UID ist zufällig, ein Fehltreffer ist ausgeschlossen. */
    public function isLive(string $uid): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM posts p JOIN post_revisions r ON r.post_id = p.id AND r.revision = p.published_revision
             WHERE p.published_revision IS NOT NULL AND p.status <> 'archived' AND r.snapshot LIKE ? LIMIT 1"
        );
        $statement->execute(['%' . $uid . '%']);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $column, string $value): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM media WHERE ' . $column . ' = ?');
        $statement->execute([$value]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['bytes'] = (int) $row['bytes'];
        $row['width'] = $row['width'] === null ? null : (int) $row['width'];
        $row['height'] = $row['height'] === null ? null : (int) $row['height'];
        $row['variants'] = json_decode((string) $row['variants'], true, 512, JSON_THROW_ON_ERROR);

        return $row;
    }
}
