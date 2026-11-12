<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use Dispatch\Auth\Scopes;
use Dispatch\Db\Transaction;
use Dispatch\Domain\Actor;
use Dispatch\Domain\AuditRepository;
use Dispatch\Domain\DomainError;
use PDO;

/** Zustimmung des Inhabers zu einer offenen Anfrage: beschreiben, erlauben oder ablehnen. Kennt kein HTTP. */
final class Consent
{
    private const OFFLINE_SCOPE = 'offline_access';

    private OAuthRepository $repository;
    private ClientRegistry $clients;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->repository = new OAuthRepository($pdo);
        $this->clients = new ClientRegistry($pdo, $config);
        $this->audit = new AuditRepository($pdo);
    }

    /**
     * @return array{request_id: string, client: array{name: string, host: string, kind: string}, redirect_host: string, loopback: bool, scopes: list<string>, offline: bool, expires_in: int}
     * @throws DomainError `not_found` | `request_expired`
     */
    public function describe(string $requestId): array
    {
        $request = $this->openRequest($requestId);
        $client = $this->storedClient($request);
        $requested = explode(' ', (string) $request['scope']);

        return [
            'request_id' => $requestId,
            'client' => ['name' => $client['name'], 'host' => $client['host'], 'kind' => $client['kind']],
            'redirect_host' => $this->redirectHost((string) $request['redirect_uri']),
            'loopback' => ClientRegistry::isLoopback((string) $request['redirect_uri']),
            'scopes' => array_values(array_intersect(Scopes::ALL, $requested)),
            'offline' => in_array(self::OFFLINE_SCOPE, $requested, true),
            'expires_in' => max(0, (int) $request['seconds_left']),
        ];
    }

    /**
     * Erlaubt (Code ausgeben) oder lehnt ab; beides nur aus `pending` heraus.
     *
     * @param list<string> $scopes gewählte `blog:*`-Scopes (bei `allow` Pflicht, Teilmenge der angefragten)
     * @return string Weiterleitungs-URL an die Rückadresse der App
     * @throws DomainError `not_found` | `request_expired` | `invalid_value`
     */
    public function decide(string $requestId, bool $allow, array $scopes, Actor $actor): string
    {
        return Transaction::run($this->pdo, function () use ($requestId, $allow, $scopes, $actor): string {
            $request = $this->openRequest($requestId);
            $client = $this->storedClient($request);

            if (!$allow) {
                return $this->deny($request, $client, $actor);
            }

            return $this->allow($request, $client, $scopes, $actor);
        });
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $client
     * @param list<string> $scopes
     */
    private function allow(array $request, array $client, array $scopes, Actor $actor): string
    {
        $requested = explode(' ', (string) $request['scope']);
        $chosen = array_values(array_intersect(Scopes::ALL, $scopes));
        $isSubset = array_diff($scopes, array_intersect(Scopes::ALL, $requested)) === [];
        if ($chosen === [] || !$isSubset) {
            throw new DomainError('invalid_value', 'Mindestens ein angefragter Scope muss gewählt sein, und es dürfen nur angefragte Scopes sein.', ['field' => 'scopes']);
        }

        $granted = in_array(self::OFFLINE_SCOPE, $requested, true) ? [...$chosen, self::OFFLINE_SCOPE] : $chosen;
        $code = bin2hex(random_bytes(32));
        $isApproved = $this->repository->transition(
            (int) $request['id'],
            OAuthRepository::STATUS_PENDING,
            OAuthRepository::STATUS_APPROVED,
            hash('sha256', $code),
            bin2hex(random_bytes(16)),
            (int) $this->config['oauth']['code_seconds'],
            implode(' ', $granted)
        );
        if (!$isApproved) {
            throw $this->expired();
        }

        $this->audit->record($actor, 'oauth.consent', 'client:' . $client['client_id'], ['scopes' => $chosen]);

        return Redirect::with((string) $request['redirect_uri'], [
            'code' => $code,
            'state' => $request['state'],
            'iss' => Discovery::base($this->config),
        ]);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $client
     */
    private function deny(array $request, array $client, Actor $actor): string
    {
        $isDenied = $this->repository->transition((int) $request['id'], OAuthRepository::STATUS_PENDING, OAuthRepository::STATUS_DENIED);
        if (!$isDenied) {
            throw $this->expired();
        }

        $this->audit->record($actor, 'oauth.deny', 'client:' . $client['client_id']);

        return Redirect::with((string) $request['redirect_uri'], [
            'error' => 'access_denied',
            'error_description' => 'Der Inhaber hat den Zugriff abgelehnt.',
            'state' => $request['state'],
            'iss' => Discovery::base($this->config),
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws DomainError
     */
    private function openRequest(string $requestId): array
    {
        $request = preg_match('/^[0-9a-f]{32}$/', $requestId) === 1 ? $this->repository->findRequest($requestId) : null;
        if ($request === null) {
            throw new DomainError('not_found', 'Diese Anfrage gibt es nicht.');
        }
        if ($request['status'] !== OAuthRepository::STATUS_PENDING || (int) $request['not_expired'] !== 1) {
            throw $this->expired();
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $request
     * @return array{client_id: string, kind: string, name: string, host: string, redirect_uris: list<string>, secret_hash: string|null}
     */
    private function storedClient(array $request): array
    {
        try {
            return $this->clients->stored((string) $request['client_id']);
        } catch (OAuthError) {
            throw $this->expired();
        }
    }

    private function redirectHost(string $redirectUri): string
    {
        $parts = parse_url($redirectUri);
        $host = (string) ($parts['host'] ?? '');

        return isset($parts['port']) ? $host . ':' . $parts['port'] : $host;
    }

    private function expired(): DomainError
    {
        return new DomainError('request_expired', 'Diese Anfrage ist abgelaufen oder schon beantwortet. Bitte in der App neu verbinden.');
    }
}
