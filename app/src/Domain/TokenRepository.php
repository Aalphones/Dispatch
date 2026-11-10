<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class TokenRepository
{
    private const KIND_MCP = 'mcp';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array{token_hash: string, name: string, scope: string, expires_days: int|null} $fields */
    public function insert(array $fields): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tokens (token_hash, kind, name, scope, expires_at, revoked, created_at)
             VALUES (?, ?, ?, ?, IF(? IS NULL, NULL, UTC_TIMESTAMP() + INTERVAL ? DAY), 0, UTC_TIMESTAMP())'
        );
        $statement->execute([
            $fields['token_hash'],
            self::KIND_MCP,
            $fields['name'],
            $fields['scope'],
            $fields['expires_days'],
            $fields['expires_days'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findActiveByHash(string $hash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, scope FROM tokens
             WHERE token_hash = ? AND kind = ? AND revoked = 0 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())'
        );
        $statement->execute([$hash, self::KIND_MCP]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function countActive(): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM tokens WHERE kind = ? AND revoked = 0 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())'
        );
        $statement->execute([self::KIND_MCP]);

        return (int) $statement->fetchColumn();
    }

    public function activeNameExists(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM tokens WHERE kind = ? AND revoked = 0 AND name = ? LIMIT 1');
        $statement->execute([self::KIND_MCP, $name]);

        return $statement->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> neueste zuerst, ohne Hash */
    public function listMcp(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, scope, expires_at, revoked, last_used_at, created_at FROM tokens WHERE kind = ? ORDER BY id DESC'
        );
        $statement->execute([self::KIND_MCP]);

        return $statement->fetchAll();
    }

    /** Widerruft nur ein vorhandenes, noch nicht widerrufenes mcp-Token. */
    public function revoke(int $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE tokens SET revoked = 1 WHERE id = ? AND kind = ? AND revoked = 0');
        $statement->execute([$id, self::KIND_MCP]);

        return $statement->rowCount() === 1;
    }

    public function touch(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?');
        $statement->execute([$id]);
    }

    public function expiresAt(int $id): ?string
    {
        $statement = $this->pdo->prepare('SELECT expires_at FROM tokens WHERE id = ?');
        $statement->execute([$id]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }
}
