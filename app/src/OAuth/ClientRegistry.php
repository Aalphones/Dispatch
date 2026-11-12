<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use Dispatch\Db\Transaction;
use Dispatch\Domain\Actor;
use Dispatch\Domain\AuditRepository;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\TokenRepository;
use Dispatch\Util\HttpClient;
use Dispatch\Util\Log;
use JsonException;
use PDO;

/** OAuth-Clients: CIMD-Clients von der Allowlist (Metadatendokument) und von Hand angelegte Rückfall-Clients. */
final class ClientRegistry
{
    private const MANUAL_ID_PREFIX = 'dcl_';
    private const MANUAL_SECRET_PREFIX = 'dcs_';
    private const MANUAL_HOST_LABEL = 'von dir angelegt';
    private const NAME_PATTERN = '/^[a-z0-9][a-z0-9-]{0,39}$/';
    private const MAX_CLIENT_ID_LENGTH = 500;
    private const MAX_NAME_LENGTH = 120;
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private OAuthRepository $repository;
    private AuditRepository $audit;
    private TokenRepository $tokens;
    private HttpClient $http;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config, ?HttpClient $http = null)
    {
        $this->repository = new OAuthRepository($pdo);
        $this->audit = new AuditRepository($pdo);
        $this->tokens = new TokenRepository($pdo);
        $this->http = $http ?? new HttpClient(
            array_merge($config['http'], $config['oauth']['http']),
            Discovery::base($config)
        );
    }

    /**
     * @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null}
     * @throws OAuthError `unbekannte_app` | `app_nicht_erreichbar`
     */
    public function resolve(string $clientId): array
    {
        if (str_starts_with($clientId, 'https://')) {
            return $this->resolveCimd($clientId);
        }
        if (str_starts_with($clientId, self::MANUAL_ID_PREFIX)) {
            return $this->resolveManual($clientId);
        }

        throw $this->unknownApp('Die App ist nicht bekannt.');
    }

    /**
     * Client aus der gespeicherten Zeile, ohne neuen Abruf (Zustimmung und Token-Endpunkt).
     *
     * @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null}
     * @throws OAuthError `unbekannte_app` (nicht gespeichert oder widerrufen)
     */
    public function stored(string $clientId): array
    {
        $row = $this->repository->findClient($clientId, 0);
        if ($row === null || (int) $row['revoked'] === 1) {
            throw $this->unknownApp('Die App ist nicht bekannt.');
        }

        return $this->describe($row, self::hostFor($clientId));
    }

    /** Anzeige-Host: bei CIMD der Host der `client_id`, beim Rückfall-Client ein fester Text. */
    public static function hostFor(string $clientId): string
    {
        if (!str_starts_with($clientId, 'https://')) {
            return self::MANUAL_HOST_LABEL;
        }

        return strtolower((string) parse_url($clientId, PHP_URL_HOST));
    }

    /** @param array{redirect_uris: list<string>} $client */
    public function redirectAllowed(array $client, string $uri): bool
    {
        if (in_array($uri, $client['redirect_uris'], true)) {
            return true;
        }

        $requested = parse_url($uri);
        if ($requested === false || ($requested['scheme'] ?? '') !== 'http' || array_intersect_key($requested, ['fragment' => 1, 'user' => 1, 'pass' => 1]) !== []) {
            return false;
        }

        foreach ($client['redirect_uris'] as $registeredUri) {
            if ($this->isLoopbackMatch(parse_url($registeredUri), $requested)) {
                return true;
            }
        }

        return false;
    }

    /** true, wenn die Rückadresse auf den eigenen Rechner zeigt (Claude Code). */
    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);

        return $parts !== false && ($parts['scheme'] ?? '') === 'http' && in_array($parts['host'] ?? '', self::LOOPBACK_HOSTS, true);
    }

    /**
     * @return array{id: int, client_id: string, client_secret: string, name: string, redirect_uris: list<string>}
     */
    public function createManual(string $name, Actor $actor): array
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new DomainError(
                'invalid_value',
                'Der Name darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten, muss mit Buchstabe oder Ziffer beginnen und höchstens 40 Zeichen lang sein (z. B. claude-app).',
                ['field' => 'name']
            );
        }

        return Transaction::join($this->pdo, function () use ($name, $actor): array {
            if ($this->repository->activeManualNameExists($name)) {
                throw new DomainError('name_taken', sprintf('Es gibt schon einen aktiven Client „%s“. Anderen Namen wählen oder den alten widerrufen.', $name), ['field' => 'name']);
            }

            $clientId = self::MANUAL_ID_PREFIX . bin2hex(random_bytes(16));
            $secret = self::MANUAL_SECRET_PREFIX . bin2hex(random_bytes(32));
            $redirectUris = array_values(array_map('strval', $this->config['oauth']['manual_redirect_uris']));
            $id = $this->repository->insertManualClient($clientId, $name, hash('sha256', $secret), $redirectUris);
            $this->audit->record($actor, 'oauth_client.create', 'oauth_client:' . $id, ['name' => $name]);

            return ['id' => $id, 'client_id' => $clientId, 'client_secret' => $secret, 'name' => $name, 'redirect_uris' => $redirectUris];
        });
    }

    /** @return list<array<string, mixed>> */
    public function listManual(): array
    {
        return $this->repository->listManual();
    }

    public function revokeManual(int $id, Actor $actor): void
    {
        Transaction::join($this->pdo, function () use ($id, $actor): void {
            $client = $this->repository->findManualById($id);
            if ($client === null || !$this->repository->revokeClient($id)) {
                throw new DomainError('not_found', sprintf('Einen aktiven Rückfall-Client mit der ID %d gibt es nicht.', $id), ['client_id' => $id]);
            }
            $this->tokens->revokeByClient((string) $client['client_id']);
            $this->audit->record($actor, 'oauth_client.revoke', 'oauth_client:' . $id, ['name' => $client['name']]);
        });
    }

    /** @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null} */
    private function resolveCimd(string $clientId): array
    {
        $host = $this->cimdHost($clientId);
        $cacheSeconds = (int) $this->config['oauth']['cimd_cache_seconds'];
        $stored = $this->repository->findClient($clientId, $cacheSeconds);
        $hasUsableRow = $stored !== null && $stored['kind'] === OAuthRepository::KIND_CIMD;

        if ($hasUsableRow && (int) $stored['is_fresh'] === 1) {
            return $this->describe($stored, $host);
        }

        try {
            $response = $this->http->get($clientId, (int) $this->config['oauth']['cimd_max_bytes']);
        } catch (DomainError $error) {
            if (!$hasUsableRow) {
                Log::warning('CIMD nicht abrufbar', ['client_id' => $clientId, 'reason' => $error->getMessage()]);

                throw new OAuthError('app_nicht_erreichbar', 'Das Client-Metadatendokument ist nicht abrufbar.');
            }
            Log::warning('CIMD nicht abrufbar, Zwischenspeicher wird genutzt', ['client_id' => $clientId, 'reason' => $error->getMessage()]);

            return $this->describe($stored, $host);
        }

        $document = $this->parseDocument($response->body, $clientId);
        $this->repository->saveCimdClient($clientId, $this->documentName($document, $host), $document['redirect_uris'], $response->body);
        $saved = $this->repository->findClient($clientId, $cacheSeconds);

        return $this->describe($saved ?? throw $this->unknownApp('Die App ist nicht bekannt.'), $host);
    }

    /** Nur `https://<Host aus der Allowlist>/<Pfad>`: kein Port, keine Zugangsdaten, kein Fragment. */
    private function cimdHost(string $clientId): string
    {
        $parts = parse_url($clientId);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isPlain = $parts !== false
            && array_intersect_key($parts, ['port' => 1, 'user' => 1, 'pass' => 1, 'fragment' => 1]) === []
            && ($parts['path'] ?? '/') !== '/' && strlen($clientId) <= self::MAX_CLIENT_ID_LENGTH;
        $isAllowed = in_array($host, $this->config['oauth']['cimd_hosts'], true);
        if (!$isPlain || !$isAllowed) {
            throw $this->unknownApp('Der Host der App ist nicht freigegeben.');
        }

        return $host;
    }

    /** @return array{redirect_uris: list<string>, client_name?: string} */
    private function parseDocument(string $body, string $clientId): array
    {
        try {
            $document = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->unknownApp('Das Client-Metadatendokument ist kein JSON.');
        }

        $redirectUris = is_array($document) ? ($document['redirect_uris'] ?? null) : null;
        $isValid = is_array($document) && !array_is_list($document)
            && ($document['client_id'] ?? null) === $clientId
            && is_array($redirectUris) && $redirectUris !== [] && array_is_list($redirectUris)
            && array_filter($redirectUris, static fn (mixed $uri): bool => !is_string($uri) || $uri === '') === []
            && (!isset($document['client_name']) || is_string($document['client_name']));
        if (!$isValid) {
            throw $this->unknownApp('Das Client-Metadatendokument ist ungültig.');
        }

        return $document;
    }

    /** @param array<string, mixed> $document */
    private function documentName(array $document, string $host): string
    {
        $name = trim((string) ($document['client_name'] ?? ''));

        return $name === '' ? $host : mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }

    /** @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null} */
    private function resolveManual(string $clientId): array
    {
        $row = $this->repository->findClient($clientId, 0);
        if ($row === null || $row['kind'] !== OAuthRepository::KIND_MANUAL || (int) $row['revoked'] === 1) {
            throw $this->unknownApp('Die App ist nicht bekannt.');
        }

        return $this->describe($row, self::MANUAL_HOST_LABEL);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null}
     */
    private function describe(array $row, string $host): array
    {
        $redirectUris = json_decode((string) $row['redirect_uris'], true, 8, JSON_THROW_ON_ERROR);

        return [
            'client_id' => (string) $row['client_id'],
            'kind' => (string) $row['kind'],
            'name' => (string) $row['name'],
            'host' => $host,
            'redirect_uris' => array_values(array_map('strval', $redirectUris)),
            'secret_hash' => $row['secret_hash'] === null ? null : (string) $row['secret_hash'],
        ];
    }

    /**
     * Loopback (RFC 8252 §7.3): Schema, Host, Pfad und Query gleich, der Port ist frei.
     *
     * @param array<string, mixed>|false $registered
     * @param array<string, mixed> $requested
     */
    private function isLoopbackMatch(array|false $registered, array $requested): bool
    {
        if ($registered === false || ($registered['scheme'] ?? '') !== 'http' || !in_array($registered['host'] ?? '', self::LOOPBACK_HOSTS, true)) {
            return false;
        }

        return ($requested['host'] ?? null) === $registered['host']
            && ($requested['path'] ?? '') === ($registered['path'] ?? '')
            && ($requested['query'] ?? '') === ($registered['query'] ?? '');
    }

    private function unknownApp(string $description): OAuthError
    {
        return new OAuthError('unbekannte_app', $description);
    }
}
