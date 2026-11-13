<?php

declare(strict_types=1);

namespace Dispatch\Api;

use DateTimeImmutable;
use DateTimeZone;
use Dispatch\Db\Connection;
use Dispatch\Domain\ScheduleClock;
use Dispatch\Domain\ScheduleService;
use Dispatch\Util\Log;
use Throwable;

/**
 * Fälligkeitsprüfung bei jeder Anfrage an die Brücke (ADR-028). Wirft nie: Ein Fehler hier darf die eigentliche
 * Anfrage nicht stören.
 */
final class ScheduleTrigger
{
    /** Wartezeit, wenn etwas fällig ist, aber nicht erschien (anderer Auslöser hält die Sperre). */
    private const RETRY_SECONDS = 60;
    private const REBUILD_RETRY_SECONDS = 300;

    /** @param array<string, mixed> $config */
    public static function run(array $config): void
    {
        try {
            $clock = new ScheduleClock((string) $config['storage_dir']);
            $state = $clock->read();
            $now = time();
            if ($state !== null && $state['next_check'] > $now && !$state['rebuild']) {
                return;
            }

            $pdo = Connection::open();
            $service = new ScheduleService($pdo, $config);
            $nowUtc = new DateTimeImmutable('@' . $now, new DateTimeZone('UTC'));

            $released = $service->releaseDue($nowUtc);
            $isRebuildOk = true;
            if ($released > 0 || ($state['rebuild'] ?? false)) {
                $isRebuildOk = Rebuild::run($config, $pdo)['rebuilt'];
            }

            $next = $service->nextCheck($nowUtc);
            if ($released === 0 && $next <= $now) {
                $next = $now + self::RETRY_SECONDS;
            }
            if (!$isRebuildOk) {
                $next = min($next, $now + self::REBUILD_RETRY_SECONDS);
            }
            $clock->write($next, !$isRebuildOk);
        } catch (Throwable $error) {
            Log::error('Fälligkeitsprüfung fehlgeschlagen', ['type' => $error::class, 'message' => $error->getMessage()]);
        }
    }
}
