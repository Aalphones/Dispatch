<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class AuditRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $details */
    public function record(Actor $actor, string $action, string $target, array $details = []): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_log (at, actor, action, target, details) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?)'
        );
        $statement->execute([
            $actor->name,
            $action,
            $target,
            $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return list<array<string, mixed>> neueste zuerst */
    public function latest(int $limit = 50): array
    {
        $statement = $this->pdo->prepare('SELECT id, at, actor, action, target, details FROM audit_log ORDER BY id DESC LIMIT ?');
        $statement->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $row['details'] = $row['details'] === null
                ? null
                : json_decode((string) $row['details'], true, 512, JSON_THROW_ON_ERROR);
            $rows[] = $row;
        }

        return $rows;
    }
}
