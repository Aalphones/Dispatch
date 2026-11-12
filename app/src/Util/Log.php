<?php

declare(strict_types=1);

namespace Dispatch\Util;

// Nie Passwörter, Tokens oder .env-Werte loggen — die Logdatei liegt zwar in der gesperrten
// App, ist aber Teil von Backups und Fehlerberichten.
final class Log
{
    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            return;
        }

        $entry = [
            'time' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($line === false) {
            return;
        }

        file_put_contents($directory . '/app.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
