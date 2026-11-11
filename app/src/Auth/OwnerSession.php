<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Db\Transaction;
use Dispatch\Domain\Actor;
use Dispatch\Domain\AuditRepository;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\TokenRepository;
use Dispatch\Util\Env;
use PDO;

/**
 * Anmeldung des Inhabers (ADR-020): Access-JWT für 15 Minuten, Refresh-Token als Cookie mit Rotation.
 * Die Rückgaben tragen den Klartext des neuen Refresh-Tokens oder null, wenn kein neuer Cookie gesetzt werden soll.
 */
final class OwnerSession
{
    public const COOKIE_NAME = 'dispatch_refresh';
    public const COOKIE_PATH = '/dispatch/api/auth';
    public const REFRESH_LIFETIME_SECONDS = 2592000;
    private const REFRESH_PREFIX = 'dref_';
    private const GRACE_SECONDS = 30;

    private TokenRepository $tokens;
    private AuditRepository $audit;
    private LoginThrottle $throttle;
    private Actor $owner;

    public function __construct(private readonly PDO $pdo)
    {
        $this->tokens = new TokenRepository($pdo);
        $this->audit = new AuditRepository($pdo);
        $this->throttle = new LoginThrottle($pdo);
        $this->owner = Actor::owner();
    }

    /** @return array{access_token: string, expires_in: int, user: string, refresh_token: string} */
    public function login(string $user, string $password, string $ip): array
    {
        $this->throttle->prune();
        $this->throttle->assertAllowed($ip);

        // Beide Prüfungen laufen immer: so verrät weder die Meldung noch die Antwortzeit, welcher Teil falsch war.
        $isUserValid = hash_equals(Env::get('DISPATCH_OWNER_USER'), $user);
        $isPasswordValid = password_verify($password, Env::get('DISPATCH_OWNER_PASSWORD_HASH'));
        if (!$isUserValid || !$isPasswordValid) {
            $this->throttle->recordFailure($ip);
            $this->audit->record($this->owner, 'auth.login_failed', 'owner');
            throw new DomainError('invalid_credentials', 'Benutzername oder Passwort stimmt nicht.');
        }

        $refreshToken = Transaction::run($this->pdo, function (): string {
            $refreshToken = $this->newRefreshToken(bin2hex(random_bytes(16)));
            $this->audit->record($this->owner, 'auth.login', 'owner');

            return $refreshToken;
        });

        return $this->accessResponse() + ['refresh_token' => $refreshToken];
    }

    /**
     * @return array{access_token: string, expires_in: int, user: string, refresh_token: string|null}
     * @throws DomainError `session_expired` (unbekannt/abgelaufen) oder `session_revoked` (Wiederverwendung)
     */
    public function refresh(?string $refreshToken): array
    {
        $token = $refreshToken === null ? null : $this->tokens->findByHash(hash('sha256', $refreshToken), TokenRepository::KIND_OWNER_REFRESH);
        if ($token === null || (!$token['revoked'] && !$token['not_expired'])) {
            throw new DomainError('session_expired', 'Die Sitzung ist abgelaufen. Bitte neu anmelden.');
        }

        if ($token['revoked']) {
            return $this->handleRevokedToken($token);
        }

        $newToken = Transaction::run($this->pdo, function () use ($token): ?string {
            // Zwei gleichzeitige Refreshs: nur einer gewinnt die Rotation, der andere fällt ins Kulanzfenster.
            if (!$this->tokens->revokeOne($token['id'], self::GRACE_SECONDS)) {
                return null;
            }

            return $this->newRefreshToken($token['family']);
        });

        return $this->accessResponse() + ['refresh_token' => $newToken];
    }

    public function logout(?string $refreshToken): void
    {
        $token = $refreshToken === null ? null : $this->tokens->findByHash(hash('sha256', $refreshToken), TokenRepository::KIND_OWNER_REFRESH);
        if ($token === null) {
            return;
        }

        $this->tokens->revokeOne($token['id'], 0);
        $this->audit->record($this->owner, 'auth.logout', 'owner');
    }

    public function logoutAll(): void
    {
        Transaction::run($this->pdo, function (): void {
            $this->tokens->revokeAllOwnerRefresh();
            $this->audit->record($this->owner, 'auth.logout_all', 'owner');
        });
    }

    /**
     * @param array{id: int, family: string, revoked: bool, not_expired: bool} $token
     * @return array{access_token: string, expires_in: int, user: string, refresh_token: null}
     */
    private function handleRevokedToken(array $token): array
    {
        if ($token['not_expired']) {
            // Kulanzfenster: der parallele Tab hat den neuen Cookie schon gesetzt.
            return $this->accessResponse() + ['refresh_token' => null];
        }

        Transaction::run($this->pdo, function () use ($token): void {
            $this->tokens->revokeFamily($token['family']);
            $this->audit->record($this->owner, 'auth.reuse', 'owner');
        });
        throw new DomainError('session_revoked', 'Die Sitzung wurde aus Sicherheitsgründen beendet. Bitte neu anmelden.');
    }

    private function newRefreshToken(string $family): string
    {
        $plain = self::REFRESH_PREFIX . bin2hex(random_bytes(32));
        $this->tokens->insertOwnerRefresh(hash('sha256', $plain), $family, self::REFRESH_LIFETIME_SECONDS);

        return $plain;
    }

    /** @return array{access_token: string, expires_in: int, user: string} */
    private function accessResponse(): array
    {
        return [
            'access_token' => Jwt::encode(Jwt::newAccessClaims()),
            'expires_in' => Jwt::LIFETIME_SECONDS,
            'user' => Env::get('DISPATCH_OWNER_USER'),
        ];
    }
}
