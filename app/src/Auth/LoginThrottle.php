<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Domain\DomainError;
use Dispatch\Util\Env;
use PDO;

/** Login-Bremse (ADR-020): gespeichert wird nur der HMAC der IP, nie die Adresse selbst. */
final class LoginThrottle
{
    public const RETRY_AFTER_SECONDS = 900;
    private const MAX_FAILURES = 10;
    private const KEEP_DAYS = 1;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @throws DomainError `too_many_attempts` ab dem 11. Versuch binnen 15 Minuten */
    public function assertAllowed(string $ip): void
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL ? SECOND'
        );
        $statement->execute([$this->hashIp($ip), self::RETRY_AFTER_SECONDS]);
        if ((int) $statement->fetchColumn() >= self::MAX_FAILURES) {
            throw new DomainError('too_many_attempts', 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.');
        }
    }

    public function recordFailure(string $ip): void
    {
        $statement = $this->pdo->prepare('INSERT INTO login_attempts (ip_hash, attempted_at) VALUES (?, UTC_TIMESTAMP())');
        $statement->execute([$this->hashIp($ip)]);
    }

    public function prune(): void
    {
        $statement = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL ? DAY');
        $statement->execute([self::KEEP_DAYS]);
    }

    private function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, Env::get('DISPATCH_JWT_SECRET'));
    }
}
