<?php

declare(strict_types=1);

namespace Dispatch\Http;

final class Response
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): never
    {
        self::raw(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $status, $headers);
    }

    /** @param array<string, string> $headers */
    public static function empty(int $status, array $headers = []): never
    {
        header('Cache-Control: no-store');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        http_response_code($status);
        exit;
    }

    /**
     * Für bereits kodiertes JSON (JSON-RPC-Antworten).
     *
     * @param array<string, string> $headers
     */
    public static function raw(string $json, int $status, array $headers = []): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        // Zuletzt: PHP setzt beim Senden von WWW-Authenticate selbst 401 und würde sonst ein 403 überschreiben.
        http_response_code($status);

        echo $json;
        exit;
    }

    /** @param array<string, string> $headers */
    public static function error(string $code, string $message, int $status, array $headers = []): never
    {
        self::json(['error' => $code, 'message' => $message], $status, $headers);
    }
}
