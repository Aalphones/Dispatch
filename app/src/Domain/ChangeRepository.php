<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class ChangeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function insert(string $entity, string $key, array $payload, string $createdBy): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO changes (entity, entity_key, payload, status, created_by, created_at)
             VALUES (?, ?, ?, \'pending\', ?, UTC_TIMESTAMP())'
        );
        $statement->execute([
            $entity,
            $key,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param bool $forUpdate sperrt die Zeile bis zum Ende der Transaktion (verhindert doppeltes Übernehmen)
     * @return array<string, mixed>|null
     */
    public function find(int $id, bool $forUpdate = false): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM changes WHERE id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /** @return list<array<string, mixed>> neueste zuerst */
    public function list(?string $status): array
    {
        if ($status === null) {
            $statement = $this->pdo->query('SELECT * FROM changes ORDER BY id DESC');
        } else {
            $statement = $this->pdo->prepare('SELECT * FROM changes WHERE status = ? ORDER BY id DESC');
            $statement->execute([$status]);
        }

        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    public function decide(int $id, string $status, string $decidedBy): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE changes SET status = ?, decided_by = ?, decided_at = UTC_TIMESTAMP() WHERE id = ? AND status = \'pending\''
        );
        $statement->execute([$status, $decidedBy, $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['payload'] = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

        return $row;
    }
}
