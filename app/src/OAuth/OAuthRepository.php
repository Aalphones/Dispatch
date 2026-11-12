<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use PDO;

/** Datenbankzugriff für `oauth_clients` und `oauth_codes` (Anfragen, Codes). */
final class OAuthRepository
{
    public const KIND_CIMD = 'cimd';
    public const KIND_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DENIED = 'denied';
    public const STATUS_USED = 'used';

    private const CLIENT_COLUMNS = 'id, client_id, kind, name, secret_hash, redirect_uris, revoked, created_at, last_used_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * `is_fresh`: Dokument jünger als `$cacheSeconds`.
     *
     * @return array<string, mixed>|null
     */
    public function findClient(string $clientId, int $cacheSeconds): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::CLIENT_COLUMNS . ', (fetched_at IS NOT NULL AND fetched_at > UTC_TIMESTAMP() - INTERVAL ? SECOND) AS is_fresh
             FROM oauth_clients WHERE client_id = ?'
        );
        $statement->execute([$cacheSeconds, $clientId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null nur Rückfall-Clients */
    public function findManualById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT ' . self::CLIENT_COLUMNS . ' FROM oauth_clients WHERE id = ? AND kind = ?');
        $statement->execute([$id, self::KIND_MANUAL]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param list<string> $redirectUris */
    public function saveCimdClient(string $clientId, string $name, array $redirectUris, string $metadataJson): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO oauth_clients (client_id, kind, name, redirect_uris, metadata, fetched_at, revoked, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), 0, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE name = VALUES(name), redirect_uris = VALUES(redirect_uris),
                                     metadata = VALUES(metadata), fetched_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            $clientId,
            self::KIND_CIMD,
            $name,
            json_encode($redirectUris, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $metadataJson,
        ]);
    }

    /** @param list<string> $redirectUris */
    public function insertManualClient(string $clientId, string $name, string $secretHash, array $redirectUris): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO oauth_clients (client_id, kind, name, secret_hash, redirect_uris, revoked, created_at)
             VALUES (?, ?, ?, ?, ?, 0, UTC_TIMESTAMP())'
        );
        $statement->execute([
            $clientId,
            self::KIND_MANUAL,
            $name,
            $secretHash,
            json_encode($redirectUris, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function activeManualNameExists(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM oauth_clients WHERE kind = ? AND revoked = 0 AND name = ? LIMIT 1');
        $statement->execute([self::KIND_MANUAL, $name]);

        return $statement->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> nicht widerrufene Rückfall-Clients, neueste zuerst, ohne Geheimnis */
    public function listManual(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, client_id, name, created_at, last_used_at FROM oauth_clients WHERE kind = ? AND revoked = 0 ORDER BY id DESC'
        );
        $statement->execute([self::KIND_MANUAL]);

        return $statement->fetchAll();
    }

    /** false, wenn der Client schon widerrufen war. */
    public function revokeClient(int $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE oauth_clients SET revoked = 1 WHERE id = ? AND revoked = 0');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    public function touchClient(string $clientId): void
    {
        $statement = $this->pdo->prepare('UPDATE oauth_clients SET last_used_at = UTC_TIMESTAMP() WHERE client_id = ?');
        $statement->execute([$clientId]);
    }

    /** @param array{request_id: string, client_id: string, redirect_uri: string, challenge: string, scope: string, resource: string|null, state: string|null, expires_seconds: int} $fields */
    public function insertRequest(array $fields): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO oauth_codes (request_id, client_id, redirect_uri, challenge, scope, resource, state, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND, UTC_TIMESTAMP())'
        );
        $statement->execute([
            $fields['request_id'],
            $fields['client_id'],
            $fields['redirect_uri'],
            $fields['challenge'],
            $fields['scope'],
            $fields['resource'],
            $fields['state'],
            self::STATUS_PENDING,
            $fields['expires_seconds'],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findRequest(string $requestId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *, (expires_at > UTC_TIMESTAMP()) AS not_expired FROM oauth_codes WHERE request_id = ?'
        );
        $statement->execute([$requestId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCodeHash(string $codeHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT *, (expires_at > UTC_TIMESTAMP()) AS not_expired FROM oauth_codes WHERE code_hash = ?'
        );
        $statement->execute([$codeHash]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Setzt den Status nur, wenn die Zeile noch `$fromStatus` hat (kein Doppel-Einlösen). Ein Code erneuert die Frist.
     *
     * @return bool true, wenn genau diese Anfrage gewechselt hat
     */
    public function transition(int $id, string $fromStatus, string $toStatus, ?string $codeHash = null, ?string $family = null, ?int $expiresSeconds = null): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE oauth_codes
             SET status = ?, code_hash = COALESCE(?, code_hash), family = COALESCE(?, family),
                 expires_at = IF(? IS NULL, expires_at, UTC_TIMESTAMP() + INTERVAL ? SECOND)
             WHERE id = ? AND status = ?'
        );
        $statement->execute([$toStatus, $codeHash, $family, $expiresSeconds, $expiresSeconds, $id, $fromStatus]);

        return $statement->rowCount() === 1;
    }

    /** Räumt Zeilen auf, die seit mehr als einem Tag abgelaufen sind. */
    public function deleteStale(): void
    {
        $this->pdo->exec('DELETE FROM oauth_codes WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }
}
