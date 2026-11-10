<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Auth\Scopes;
use Dispatch\Db\Transaction;
use PDO;

/** MCP-Tokens: Klartext `dmcp_` + 64 Hex, gespeichert wird nur der SHA-256-Hash (ADR-017). */
final class TokenService
{
    private const PREFIX = 'dmcp_';
    private const PLAIN_LENGTH = 69;
    private const NAME_PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';
    private const MAX_DAYS = 3650;

    private TokenRepository $tokens;
    private AuditRepository $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->tokens = new TokenRepository($pdo);
        $this->audit = new AuditRepository($pdo);
    }

    /**
     * @param list<string>|string $scopes
     * @return array{token: string, id: int, name: string, scopes: list<string>, expires_at: string|null}
     */
    public function create(string $name, array|string $scopes, ?int $expiresDays, Actor $actor): array
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new DomainError(
                'invalid_value',
                'Der Name darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten, muss mit Buchstabe oder Ziffer beginnen und höchstens 40 Zeichen lang sein (z. B. claude-code).',
                ['field' => 'name']
            );
        }
        $scopeList = Scopes::parse($scopes);
        if ($expiresDays !== null && ($expiresDays < 1 || $expiresDays > self::MAX_DAYS)) {
            throw new DomainError('invalid_value', sprintf('Die Laufzeit muss zwischen 1 und %d Tagen liegen (oder ohne Ablauf).', self::MAX_DAYS), ['field' => 'expires_days']);
        }

        return Transaction::join($this->pdo, function () use ($name, $scopeList, $expiresDays, $actor): array {
            if ($this->tokens->activeNameExists($name)) {
                throw new DomainError('name_taken', sprintf('Es gibt schon ein aktives Token „%s“. Anderen Namen wählen oder das alte widerrufen.', $name), ['field' => 'name']);
            }

            $plain = self::PREFIX . bin2hex(random_bytes(32));
            $id = $this->tokens->insert([
                'token_hash' => hash('sha256', $plain),
                'name' => $name,
                'scope' => implode(' ', $scopeList),
                'expires_days' => $expiresDays,
            ]);
            $expiresAt = $this->tokens->expiresAt($id);
            $this->audit->record($actor, 'token.create', 'token:' . $id, [
                'name' => $name,
                'scopes' => $scopeList,
                'expires_at' => $expiresAt,
            ]);

            return ['token' => $plain, 'id' => $id, 'name' => $name, 'scopes' => $scopeList, 'expires_at' => $expiresAt];
        });
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $entries = [];
        foreach ($this->tokens->listMcp() as $row) {
            $entries[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'scopes' => explode(' ', (string) $row['scope']),
                'expires_at' => $row['expires_at'],
                'revoked' => (bool) $row['revoked'],
                'last_used_at' => $row['last_used_at'],
                'created_at' => $row['created_at'],
            ];
        }

        return $entries;
    }

    public function revoke(int $id, Actor $actor): void
    {
        Transaction::join($this->pdo, function () use ($id, $actor): void {
            if (!$this->tokens->revoke($id)) {
                throw new DomainError('not_found', sprintf('Ein aktives Token mit der ID %d gibt es nicht (php bin/dispatch token:list zeigt die IDs).', $id), ['token_id' => $id]);
            }
            $this->audit->record($actor, 'token.revoke', 'token:' . $id);
        });
    }

    /** @return array{id: int, name: string, scopes: list<string>}|null */
    public function authenticate(string $plain): ?array
    {
        if (strlen($plain) !== self::PLAIN_LENGTH || !str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        $row = $this->tokens->findActiveByHash(hash('sha256', $plain));
        if ($row === null) {
            return null;
        }
        $this->tokens->touch((int) $row['id']);

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'scopes' => explode(' ', (string) $row['scope']),
        ];
    }
}
