<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Site\Builder;
use Dispatch\Util\Log;
use PDO;
use Throwable;

/**
 * Neubau der Website nach einer Redaktions-Aktion (ADR-021). Scheitert der Bau, bleibt die Aktion bestehen:
 * die Antwort meldet es, rückgängig gemacht wird nichts.
 */
final class Rebuild
{
    /** Kürzer als die 30 s des MCP-Tools: Der Inhaber wartet im Browser, und das PHP-Zeitlimit darf nicht vor dem Lock-Timeout greifen. */
    private const LOCK_WAIT_SECONDS = 10;

    /**
     * @param array<string, mixed> $config
     * @return array{rebuilt: bool, rebuild_error: string|null}
     */
    public static function run(array $config, PDO $pdo): array
    {
        try {
            (new Builder($config, $pdo, self::LOCK_WAIT_SECONDS))->build();
        } catch (Throwable $error) {
            Log::error('Neubau nach Redaktions-Aktion fehlgeschlagen', ['type' => $error::class, 'message' => $error->getMessage()]);

            return ['rebuilt' => false, 'rebuild_error' => $error->getMessage()];
        }

        return ['rebuilt' => true, 'rebuild_error' => null];
    }
}
