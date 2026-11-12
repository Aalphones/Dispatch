<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use Dispatch\Auth\Scopes;
use Dispatch\Db\Connection;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Log;

/**
 * `GET /dispatch/oauth/authorize`: prüft Client und Rückadresse, legt die Anfrage an und schickt den Inhaber zur Zustimmungsseite.
 * Scheitert Client oder Rückadresse, geht es nie an die Rückadresse, sondern auf die Fehlerseite des Blogs.
 */
final class AuthorizeController
{
    private const CONSENT_PATH = '/anmelden/zustimmung/';
    private const OFFLINE_SCOPE = 'offline_access';
    private const CHALLENGE_PATTERN = '/^[A-Za-z0-9_-]{43,128}$/';
    private const MAX_REDIRECT_LENGTH = 500;
    private const MAX_STATE_LENGTH = 500;
    private const MAX_HOST_LENGTH = 253;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        $pdo = Connection::open();
        $repository = new OAuthRepository($pdo);
        $repository->deleteStale();

        $clientId = (string) $request->query('client_id');
        $redirectUri = (string) $request->query('redirect_uri');

        $registry = new ClientRegistry($pdo, $this->config);
        try {
            $client = $registry->resolve($clientId);
        } catch (OAuthError $error) {
            $this->showError($error->error, $clientId);
        }
        if ($redirectUri === '' || strlen($redirectUri) > self::MAX_REDIRECT_LENGTH || !$registry->redirectAllowed($client, $redirectUri)) {
            $this->showError('falsche_rueckadresse', $clientId);
        }

        try {
            $fields = $this->validate($request);
        } catch (OAuthError $error) {
            $this->redirectError($redirectUri, $error, $request->query('state'));
        }

        $requestId = bin2hex(random_bytes(16));
        $repository->insertRequest([
            'request_id' => $requestId,
            'client_id' => $client['client_id'],
            'redirect_uri' => $redirectUri,
            'challenge' => $fields['challenge'],
            'scope' => implode(' ', $fields['scopes']),
            'resource' => $fields['resource'],
            'state' => $fields['state'],
            'expires_seconds' => (int) $this->config['oauth']['request_seconds'],
        ]);

        Response::empty(302, ['Location' => Redirect::with($this->consentUrl(), ['anfrage' => $requestId])]);
    }

    /**
     * @return array{challenge: string, scopes: list<string>, resource: string|null, state: string|null}
     * @throws OAuthError
     */
    private function validate(Request $request): array
    {
        if ($request->query('response_type') !== 'code') {
            throw new OAuthError('unsupported_response_type', 'Nur response_type=code wird unterstützt.');
        }

        $challenge = (string) $request->query('code_challenge');
        if (preg_match(self::CHALLENGE_PATTERN, $challenge) !== 1 || $request->query('code_challenge_method') !== 'S256') {
            throw new OAuthError('invalid_request', 'PKCE mit code_challenge und code_challenge_method=S256 ist Pflicht.');
        }

        $resource = $request->query('resource');
        if ($resource !== null && $resource !== Discovery::base($this->config) . '/dispatch/mcp') {
            throw new OAuthError('invalid_target', 'Die Ressource ist nicht dieser MCP-Endpunkt.');
        }

        $state = $request->query('state');
        if ($state !== null && strlen($state) > self::MAX_STATE_LENGTH) {
            throw new OAuthError('invalid_request', 'Der Wert state ist zu lang.');
        }

        return ['challenge' => $challenge, 'scopes' => $this->requestedScopes($request->query('scope')), 'resource' => $resource, 'state' => $state];
    }

    /**
     * Ohne `scope`: Standard plus `offline_access`. Reihenfolge wie `Scopes::ALL`, `offline_access` zuletzt.
     *
     * @return list<string>
     * @throws OAuthError `invalid_scope`
     */
    private function requestedScopes(?string $scope): array
    {
        if ($scope === null || trim($scope) === '') {
            return [...$this->config['oauth']['default_scopes'], self::OFFLINE_SCOPE];
        }

        $requested = array_values(array_unique(preg_split('/\s+/', trim($scope)) ?: []));
        $known = [...Scopes::ALL, self::OFFLINE_SCOPE];
        $unknown = array_diff($requested, $known);
        $hasBlogScope = array_intersect(Scopes::ALL, $requested) !== [];
        if ($unknown !== [] || !$hasBlogScope) {
            throw new OAuthError('invalid_scope', 'Gültig sind blog:read, blog:write, blog:admin und offline_access; mindestens ein blog:-Scope ist nötig.');
        }

        return array_values(array_intersect($known, $requested));
    }

    private function consentUrl(): string
    {
        return Discovery::base($this->config) . self::CONSENT_PATH;
    }

    private function showError(string $code, string $clientId): never
    {
        Log::info('OAuth-Anfrage abgewiesen', ['error' => $code, 'client_id' => mb_substr($clientId, 0, self::MAX_REDIRECT_LENGTH)]);

        $host = null;
        $parsedHost = str_starts_with($clientId, 'http') ? parse_url($clientId, PHP_URL_HOST) : null;
        if ($code === 'unbekannte_app' && is_string($parsedHost) && strlen($parsedHost) <= self::MAX_HOST_LENGTH) {
            $host = strtolower($parsedHost);
        }

        Response::empty(302, ['Location' => Redirect::with($this->consentUrl(), ['fehler' => $code, 'host' => $host])]);
    }

    private function redirectError(string $redirectUri, OAuthError $error, ?string $state): never
    {
        $location = Redirect::with($redirectUri, [
            'error' => $error->error,
            'error_description' => $error->description,
            'state' => $state,
            'iss' => Discovery::base($this->config),
        ]);

        Response::empty(302, ['Location' => $location]);
    }
}
