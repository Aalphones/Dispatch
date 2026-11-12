<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class TokenRepository
{
    private const KIND_MCP = 'mcp';
    public const KIND_OWNER_REFRESH = 'owner_refresh';
    public const KIND_OAUTH_ACCESS = 'oauth_access';
    public const KIND_OAUTH_REFRESH = 'oauth_refresh';

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

    public function insertOwnerRefresh(string $hash, string $family, int $lifetimeSeconds): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO tokens (token_hash, kind, name, scope, family, expires_at, revoked, created_at)
             VALUES (?, ?, 'owner', '', ?, UTC_TIMESTAMP() + INTERVAL ? SECOND, 0, UTC_TIMESTAMP())"
        );
        $statement->execute([$hash, self::KIND_OWNER_REFRESH, $family, $lifetimeSeconds]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ohne Gültigkeitsfilter: die Anmeldung unterscheidet gültig, Kulanzfenster und Wiederverwendung selbst.
     *
     * @return array{id: int, family: string, revoked: bool, not_expired: bool}|null
     */
    public function findByHash(string $hash, string $kind): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family, revoked, (expires_at IS NOT NULL AND expires_at > UTC_TIMESTAMP()) AS not_expired
             FROM tokens WHERE token_hash = ? AND kind = ?'
        );
        $statement->execute([$hash, $kind]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'family' => (string) $row['family'],
            'revoked' => (int) $row['revoked'] === 1,
            'not_expired' => (int) $row['not_expired'] === 1,
        ];
    }

    /** Widerruft ein noch gültiges Token; `expires_at` wird auf jetzt + `graceSeconds` gesetzt (Kulanzfenster). false, wenn es schon widerrufen war. */
    public function revokeOne(int $id, int $graceSeconds): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE tokens SET revoked = 1, expires_at = UTC_TIMESTAMP() + INTERVAL ? SECOND WHERE id = ? AND revoked = 0'
        );
        $statement->execute([$graceSeconds, $id]);

        return $statement->rowCount() === 1;
    }

    public function revokeFamily(string $family): void
    {
        $statement = $this->pdo->prepare('UPDATE tokens SET revoked = 1, expires_at = UTC_TIMESTAMP() WHERE family = ?');
        $statement->execute([$family]);
    }

    public function insertOAuth(string $kind, string $hash, string $name, string $clientId, string $scope, string $family, int $lifetimeSeconds): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tokens (token_hash, kind, name, scope, client_id, family, expires_at, revoked, created_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND, 0, UTC_TIMESTAMP())'
        );
        $statement->execute([$hash, $kind, $name, $scope, $clientId, $family, $lifetimeSeconds]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ohne Gültigkeitsfilter: der Refresh unterscheidet gültig, Kulanzfenster und Wiederverwendung selbst.
     *
     * @return array{id: int, family: string, client_id: string, name: string, scope: string, revoked: bool, not_expired: bool}|null
     */
    public function findOAuthByHash(string $hash, string $kind): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, family, client_id, name, scope, revoked, (expires_at IS NOT NULL AND expires_at > UTC_TIMESTAMP()) AS not_expired
             FROM tokens WHERE token_hash = ? AND kind = ?'
        );
        $statement->execute([$hash, $kind]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'family' => (string) $row['family'],
            'client_id' => (string) $row['client_id'],
            'name' => (string) $row['name'],
            'scope' => (string) $row['scope'],
            'revoked' => (int) $row['revoked'] === 1,
            'not_expired' => (int) $row['not_expired'] === 1,
        ];
    }

    /** Gültiges Access-Token eines nicht widerrufenen Clients. */
    public function findActiveOAuthAccess(string $hash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.name, t.scope FROM tokens t
             JOIN oauth_clients c ON c.client_id = t.client_id
             WHERE t.token_hash = ? AND t.kind = ? AND t.revoked = 0 AND t.expires_at > UTC_TIMESTAMP() AND c.revoked = 0'
        );
        $statement->execute([$hash, self::KIND_OAUTH_ACCESS]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** Alle Refresh-Tokens der Familie außer `$keepId` sofort widerrufen (es bleibt genau ein gültiger Zweig). */
    public function revokeOtherRefresh(string $family, int $keepId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tokens SET revoked = 1, expires_at = UTC_TIMESTAMP() WHERE family = ? AND kind = ? AND revoked = 0 AND id <> ?'
        );
        $statement->execute([$family, self::KIND_OAUTH_REFRESH, $keepId]);
    }

    /**
     * Je Familie mit mindestens einem gültigen Access- oder Refresh-Token; `scope` vom jüngsten Token.
     *
     * @return list<array<string, mixed>> neueste zuerst
     */
    public function listGrants(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.family, MAX(t.client_id) AS client_id, MAX(t.name) AS name,
                    MIN(t.created_at) AS connected_at, MAX(t.last_used_at) AS last_used_at,
                    (SELECT latest.scope FROM tokens latest WHERE latest.family = t.family AND latest.kind IN (?, ?) ORDER BY latest.id DESC LIMIT 1) AS scope
             FROM tokens t
             WHERE t.kind IN (?, ?)
             GROUP BY t.family
             HAVING SUM(t.revoked = 0 AND t.expires_at > UTC_TIMESTAMP()) > 0
             ORDER BY MAX(t.id) DESC'
        );
        $statement->execute([self::KIND_OAUTH_ACCESS, self::KIND_OAUTH_REFRESH, self::KIND_OAUTH_ACCESS, self::KIND_OAUTH_REFRESH]);

        return $statement->fetchAll();
    }

    /** @return array{client_id: string, name: string}|null irgendein OAuth-Token der Familie */
    public function findOAuthFamily(string $family): ?array
    {
        $statement = $this->pdo->prepare('SELECT client_id, name FROM tokens WHERE family = ? AND kind IN (?, ?) LIMIT 1');
        $statement->execute([$family, self::KIND_OAUTH_ACCESS, self::KIND_OAUTH_REFRESH]);
        $row = $statement->fetch();

        return $row === false ? null : ['client_id' => (string) $row['client_id'], 'name' => (string) $row['name']];
    }

    /** Widerruft alle OAuth-Tokens eines Clients (Access und Refresh, jede Familie). */
    public function revokeByClient(string $clientId): void
    {
        $statement = $this->pdo->prepare('UPDATE tokens SET revoked = 1, expires_at = UTC_TIMESTAMP() WHERE client_id = ? AND revoked = 0');
        $statement->execute([$clientId]);
    }

    public function revokeAllOwnerRefresh(): void
    {
        $statement = $this->pdo->prepare('UPDATE tokens SET revoked = 1, expires_at = UTC_TIMESTAMP() WHERE kind = ?');
        $statement->execute([self::KIND_OWNER_REFRESH]);
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
