<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\LoginThrottle;
use Dispatch\Auth\OwnerSession;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/** Anmeldung des Inhabers unter `/dispatch/api/auth/` (ADR-020). */
final class AuthController
{
    private const MIN_SECRET_LENGTH = 64;

    public function login(Request $request): never
    {
        $this->requireConfiguration();

        try {
            $body = $request->json();
            $user = $body['user'] ?? null;
            $password = $body['password'] ?? null;
            if (!is_string($user) || !is_string($password) || $user === '' || $password === '') {
                throw new DomainError('invalid_credentials', 'Benutzername oder Passwort stimmt nicht.');
            }

            $result = $this->session()->login($user, $password, $request->ip());
        } catch (DomainError $error) {
            $this->fail($error);
        }

        Response::cookie(OwnerSession::COOKIE_NAME, $result['refresh_token'], OwnerSession::REFRESH_LIFETIME_SECONDS, OwnerSession::COOKIE_PATH);
        Response::json([
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'user' => $result['user'],
        ]);
    }

    public function refresh(Request $request): never
    {
        $this->requireConfiguration();
        $this->requireSameOrigin($request);

        try {
            $result = $this->session()->refresh($request->cookie(OwnerSession::COOKIE_NAME));
        } catch (DomainError $error) {
            Response::cookie(OwnerSession::COOKIE_NAME, '', 0, OwnerSession::COOKIE_PATH);
            $this->fail($error);
        }

        if ($result['refresh_token'] !== null) {
            Response::cookie(OwnerSession::COOKIE_NAME, $result['refresh_token'], OwnerSession::REFRESH_LIFETIME_SECONDS, OwnerSession::COOKIE_PATH);
        }
        Response::json([
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'user' => $result['user'],
        ]);
    }

    public function logout(Request $request): never
    {
        $this->requireConfiguration();
        $this->requireMarkerHeader($request);

        $this->session()->logout($request->cookie(OwnerSession::COOKIE_NAME));
        Response::cookie(OwnerSession::COOKIE_NAME, '', 0, OwnerSession::COOKIE_PATH);
        Response::empty(204);
    }

    public function logoutAll(Request $request): never
    {
        $this->requireConfiguration();
        ApiAuth::owner($request);

        $this->session()->logoutAll();
        Response::cookie(OwnerSession::COOKIE_NAME, '', 0, OwnerSession::COOKIE_PATH);
        Response::empty(204);
    }

    private function session(): OwnerSession
    {
        return new OwnerSession(Connection::open());
    }

    private function requireConfiguration(): void
    {
        $isConfigured = Env::get('DISPATCH_OWNER_USER') !== ''
            && Env::get('DISPATCH_OWNER_PASSWORD_HASH') !== ''
            && strlen(Env::get('DISPATCH_JWT_SECRET')) >= self::MIN_SECRET_LENGTH;
        if (!$isConfigured) {
            Response::error(
                'not_configured',
                'Die Anmeldung ist nicht eingerichtet: DISPATCH_OWNER_USER, DISPATCH_OWNER_PASSWORD_HASH und DISPATCH_JWT_SECRET (mindestens 64 Zeichen) fehlen in der .env.',
                503
            );
        }
    }

    /** Schutz vor fremden Seiten: der Cookie ist SameSite=Strict, zusätzlich muss eine eigene Anfrage erkennbar sein. */
    private function requireSameOrigin(Request $request): void
    {
        $this->requireMarkerHeader($request);

        $parts = parse_url(Env::get('DISPATCH_BASE_URL'));
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        if ($host === '' || $request->header('Origin') !== $scheme . '://' . $host . $port) {
            Response::error('forbidden_origin', 'Die Anfrage kommt nicht von dieser Website.', 403);
        }
    }

    private function requireMarkerHeader(Request $request): void
    {
        if ($request->header('X-Dispatch') !== '1') {
            Response::error('forbidden_origin', 'Der Header X-Dispatch: 1 fehlt.', 403);
        }
    }

    private function fail(DomainError $error): never
    {
        match ($error->errorCode) {
            'too_many_attempts' => Response::error($error->errorCode, $error->getMessage(), 429, ['Retry-After' => (string) LoginThrottle::RETRY_AFTER_SECONDS]),
            'invalid_json' => Response::error($error->errorCode, $error->getMessage(), 400),
            default => Response::error($error->errorCode, $error->getMessage(), 401),
        };
    }
}
