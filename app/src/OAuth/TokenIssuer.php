<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use Dispatch\Auth\Scopes;
use Dispatch\Db\Transaction;
use Dispatch\Domain\Actor;
use Dispatch\Domain\AuditRepository;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\TokenRepository;
use PDO;

/** Gibt OAuth-Tokens aus (Code-Tausch, Refresh mit Rotation), widerruft sie und prüft Access-Tokens am MCP-Endpunkt. Kennt kein HTTP. */
final class TokenIssuer
{
    public const ACCESS_PREFIX = 'doa_';
    private const REFRESH_PREFIX = 'dor_';
    private const PLAIN_LENGTH = 68;
    private const OFFLINE_SCOPE = 'offline_access';

    private OAuthRepository $repository;
    private TokenRepository $tokens;
    private AuditRepository $audit;
    private ClientRegistry $clients;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->repository = new OAuthRepository($pdo);
        $this->tokens = new TokenRepository($pdo);
        $this->audit = new AuditRepository($pdo);
        $this->clients = new ClientRegistry($pdo, $config);
    }

    /**
     * @param array<string, mixed> $form
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string, refresh_token?: string}
     * @throws OAuthError
     */
    public function exchangeCode(array $form): array
    {
        $code = $this->required($form, 'code');
        $redirectUri = $this->required($form, 'redirect_uri');
        $verifier = $this->required($form, 'code_verifier');
        $client = $this->authenticateClient($form);

        $row = $this->repository->findByCodeHash(hash('sha256', $code));
        if ($row === null) {
            throw $this->invalidGrant('Der Code ist unbekannt.');
        }
        if ($row['status'] === OAuthRepository::STATUS_USED) {
            $this->revokeReusedCode($row, $client['name']);
            throw $this->invalidGrant('Der Code wurde schon eingelöst.');
        }
        if ($row['status'] !== OAuthRepository::STATUS_APPROVED || (int) $row['not_expired'] !== 1) {
            throw $this->invalidGrant('Der Code ist abgelaufen.');
        }

        $isMatching = $row['client_id'] === $client['client_id'] && $row['redirect_uri'] === $redirectUri;
        if (!$isMatching || !hash_equals((string) $row['challenge'], self::challengeFor($verifier))) {
            throw $this->invalidGrant('Client, redirect_uri oder code_verifier passen nicht zum Code.');
        }
        $this->assertResource($form['resource'] ?? null);

        return Transaction::run($this->pdo, function () use ($row, $client): array {
            if (!$this->repository->transition((int) $row['id'], OAuthRepository::STATUS_APPROVED, OAuthRepository::STATUS_USED)) {
                throw $this->invalidGrant('Der Code wurde schon eingelöst.');
            }

            $scopes = explode(' ', (string) $row['scope']);
            $blogScopes = implode(' ', array_values(array_diff($scopes, [self::OFFLINE_SCOPE])));
            $issued = $this->issuePair($client, $blogScopes, (string) $row['family'], in_array(self::OFFLINE_SCOPE, $scopes, true));
            $this->audit->record(Actor::oauth($client['name']), 'oauth.token', 'client:' . $client['client_id'], ['scopes' => explode(' ', $blogScopes)]);
            $this->repository->touchClient($client['client_id']);

            return $issued['response'];
        });
    }

    /**
     * Rotation wie bei der Anmeldung des Inhabers (ADR-020), angepasst auf OAuth.
     *
     * @param array<string, mixed> $form
     * @return array{access_token: string, token_type: string, expires_in: int, scope: string, refresh_token?: string}
     * @throws OAuthError
     */
    public function refresh(array $form): array
    {
        $plain = $this->required($form, 'refresh_token');
        $client = $this->authenticateClient($form);

        $token = strlen($plain) === self::PLAIN_LENGTH && str_starts_with($plain, self::REFRESH_PREFIX)
            ? $this->tokens->findOAuthByHash(hash('sha256', $plain), TokenRepository::KIND_OAUTH_REFRESH)
            : null;
        if ($token === null || $token['client_id'] !== $client['client_id']) {
            throw $this->invalidGrant('Das Refresh-Token ist unbekannt.');
        }
        if ($token['revoked'] && !$token['not_expired']) {
            $this->revokeReusedRefresh($token);
            throw $this->invalidGrant('Das Refresh-Token wurde schon verwendet. Die Verbindung wurde beendet; bitte neu verbinden.');
        }
        if (!$token['revoked'] && !$token['not_expired']) {
            throw $this->invalidGrant('Das Refresh-Token ist abgelaufen.');
        }

        $scope = $this->narrowedScope($form['scope'] ?? null, $token['scope']);

        return Transaction::run($this->pdo, function () use ($token, $client, $scope): array {
            // Zwei gleichzeitige Refreshs: nur einer gewinnt die Rotation, der andere fällt ins Kulanzfenster.
            $isRotation = !$token['revoked'] && $this->tokens->revokeOne($token['id'], (int) $this->config['oauth']['grace_seconds']);
            $issued = $this->issuePair($client, $scope, $token['family'], true);
            if (!$isRotation) {
                // Kulanzfenster: das Token des ersten Refreshs wird ungültig, es bleibt genau ein Zweig.
                $this->tokens->revokeOtherRefresh($token['family'], (int) $issued['refresh_id']);
            }
            $this->repository->touchClient($client['client_id']);

            return $issued['response'];
        });
    }

    /** RFC 7009: Token (Access oder Refresh) samt Familie widerrufen, wenn es zum Client gehört; sonst stillschweigend nichts. */
    public function revoke(string $plain, ?string $clientId, ?string $secret): void
    {
        $kind = match (true) {
            str_starts_with($plain, self::ACCESS_PREFIX) => TokenRepository::KIND_OAUTH_ACCESS,
            str_starts_with($plain, self::REFRESH_PREFIX) => TokenRepository::KIND_OAUTH_REFRESH,
            default => null,
        };
        $token = $kind === null ? null : $this->tokens->findOAuthByHash(hash('sha256', $plain), $kind);
        if ($token === null || ($clientId !== null && $clientId !== $token['client_id'])) {
            return;
        }

        try {
            $this->authenticateClient(['client_id' => $token['client_id'], 'client_secret' => $secret]);
        } catch (OAuthError) {
            return;
        }

        Transaction::run($this->pdo, function () use ($token): void {
            $this->tokens->revokeFamily($token['family']);
            $this->audit->record(Actor::oauth($token['name']), 'oauth.revoke', 'client:' . $token['client_id']);
        });
    }

    /** @return array{id: int, name: string, scopes: list<string>}|null */
    public function authenticate(string $plain): ?array
    {
        if (strlen($plain) !== self::PLAIN_LENGTH || !str_starts_with($plain, self::ACCESS_PREFIX)) {
            return null;
        }

        $row = $this->tokens->findActiveOAuthAccess(hash('sha256', $plain));
        if ($row === null) {
            return null;
        }
        $this->tokens->touch((int) $row['id']);

        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'scopes' => explode(' ', (string) $row['scope'])];
    }

    /** @return list<array{family: string, client_name: string, client_host: string, scopes: list<string>, connected_at: string, last_used_at: string|null}> */
    public function grants(): array
    {
        $grants = [];
        foreach ($this->tokens->listGrants() as $row) {
            $grants[] = [
                'family' => (string) $row['family'],
                'client_name' => (string) $row['name'],
                'client_host' => ClientRegistry::hostFor((string) $row['client_id']),
                'scopes' => explode(' ', (string) $row['scope']),
                'connected_at' => (string) $row['connected_at'],
                'last_used_at' => $row['last_used_at'] === null ? null : (string) $row['last_used_at'],
            ];
        }

        return $grants;
    }

    /** @throws DomainError `not_found` */
    public function disconnect(string $family, Actor $actor): void
    {
        $known = preg_match('/^[0-9a-f]{32}$/', $family) === 1 ? $this->tokens->findOAuthFamily($family) : null;
        if ($known === null) {
            throw new DomainError('not_found', 'Diese verbundene App gibt es nicht.');
        }

        Transaction::run($this->pdo, function () use ($family, $known, $actor): void {
            $this->tokens->revokeFamily($family);
            $this->audit->record($actor, 'oauth.disconnect', 'client:' . $known['client_id']);
        });
    }

    /** `BASE64URL(SHA256(verifier))` (RFC 7636, S256). */
    private static function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Rückfall-Clients brauchen ihr Geheimnis, CIMD-Clients sind öffentlich. Kein Netzabruf: der Client wurde bei /authorize aufgelöst.
     *
     * @param array<string, mixed> $form
     * @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null}
     * @throws OAuthError `invalid_request` | `invalid_client`
     */
    private function authenticateClient(array $form): array
    {
        try {
            $client = $this->clients->stored($this->required($form, 'client_id'));
        } catch (OAuthError) {
            throw new OAuthError('invalid_client', 'Der Client ist unbekannt oder widerrufen.', 401);
        }

        if ($client['kind'] !== OAuthRepository::KIND_MANUAL) {
            return $client;
        }
        $secret = $form['client_secret'] ?? null;
        $isSecretValid = is_string($secret) && $secret !== '' && hash_equals((string) $client['secret_hash'], hash('sha256', $secret));
        if (!$isSecretValid) {
            throw new OAuthError('invalid_client', 'Das Client-Geheimnis fehlt oder stimmt nicht.', 401);
        }

        return $client;
    }

    /**
     * @param array{client_id: string, name: string} $client
     * @return array{response: array{access_token: string, token_type: string, expires_in: int, scope: string, refresh_token?: string}, refresh_id: int|null}
     */
    private function issuePair(array $client, string $scope, string $family, bool $withRefresh): array
    {
        $accessSeconds = (int) $this->config['oauth']['access_seconds'];
        $access = self::ACCESS_PREFIX . bin2hex(random_bytes(32));
        $this->tokens->insertOAuth(TokenRepository::KIND_OAUTH_ACCESS, hash('sha256', $access), $client['name'], $client['client_id'], $scope, $family, $accessSeconds);

        $response = ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => $accessSeconds, 'scope' => $scope];
        $refreshId = null;
        if ($withRefresh) {
            $refresh = self::REFRESH_PREFIX . bin2hex(random_bytes(32));
            $refreshId = $this->tokens->insertOAuth(
                TokenRepository::KIND_OAUTH_REFRESH,
                hash('sha256', $refresh),
                $client['name'],
                $client['client_id'],
                $scope,
                $family,
                (int) $this->config['oauth']['refresh_seconds']
            );
            $response['refresh_token'] = $refresh;
        }

        return ['response' => $response, 'refresh_id' => $refreshId];
    }

    /** @throws OAuthError `invalid_scope` */
    private function narrowedScope(mixed $requested, string $current): string
    {
        if (!is_string($requested) || trim($requested) === '') {
            return $current;
        }

        $wanted = array_values(array_unique(preg_split('/\s+/', trim($requested)) ?: []));
        $allowed = explode(' ', $current);
        if (array_diff($wanted, $allowed) !== []) {
            throw new OAuthError('invalid_scope', 'Der Scope geht über den bisher erteilten hinaus.');
        }

        return implode(' ', array_values(array_intersect(Scopes::ALL, $wanted)));
    }

    /** @throws OAuthError `invalid_target` */
    private function assertResource(mixed $resource): void
    {
        if ($resource === null || $resource === '') {
            return;
        }
        if ($resource !== Discovery::base($this->config) . '/dispatch/mcp') {
            throw new OAuthError('invalid_target', 'Die Ressource ist nicht dieser MCP-Endpunkt.');
        }
    }

    /**
     * @param array<string, mixed> $form
     * @throws OAuthError `invalid_request`
     */
    private function required(array $form, string $name): string
    {
        $value = $form[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new OAuthError('invalid_request', sprintf('Der Parameter %s fehlt.', $name));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function revokeReusedCode(array $row, string $clientName): void
    {
        if ($row['family'] === null) {
            return;
        }

        Transaction::run($this->pdo, function () use ($row, $clientName): void {
            $this->tokens->revokeFamily((string) $row['family']);
            $this->audit->record(Actor::oauth($clientName),'oauth.reuse', 'client:' . $row['client_id'], ['via' => 'code']);
        });
    }

    /** @param array{family: string, client_id: string, name: string} $token */
    private function revokeReusedRefresh(array $token): void
    {
        Transaction::run($this->pdo, function () use ($token): void {
            $this->tokens->revokeFamily($token['family']);
            $this->audit->record(Actor::oauth($token['name']), 'oauth.reuse', 'client:' . $token['client_id'], ['via' => 'refresh']);
        });
    }

    private function invalidGrant(string $description): OAuthError
    {
        return new OAuthError('invalid_grant', $description);
    }
}
