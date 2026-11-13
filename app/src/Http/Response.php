<?php

declare(strict_types=1);

namespace Dispatch\Http;

use Dispatch\Util\Env;

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

    /** Vor jeder Ausgabe aufrufen (Response::json/empty beenden die Anfrage). `maxAge` 0 löscht den Cookie. */
    public static function cookie(string $name, string $value, int $maxAge, string $path): void
    {
        setcookie($name, $value, [
            'expires' => $maxAge > 0 ? time() + $maxAge : 1,
            'path' => $path,
            'secure' => Env::get('DISPATCH_INSECURE_COOKIES') !== '1',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /** @param array<string, string> $headers */
    public static function empty(int $status, array $headers = []): never
    {
        header('Cache-Control: no-store');
        // Ohne eigenen Typ setzt PHP text/html; strenge MCP-Clients lehnen die leere 202 darauf ab.
        header('Content-Type: application/json; charset=utf-8');
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

    /**
     * Liefert eine Datei aus; ohne `$withBody` (HEAD) nur die Kopfzeilen samt Länge.
     * Bewusst ohne `Cache-Control: no-store`: die Aufrufer setzen ihre eigene Zwischenspeicher-Regel.
     *
     * @param array<string, string> $headers
     */
    public static function file(string $path, string $contentType, array $headers, bool $withBody): never
    {
        http_response_code(200);
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($withBody) {
            readfile($path);
        }
        exit;
    }

    /** @param array<string, string> $headers */
    public static function notModified(array $headers): never
    {
        http_response_code(304);
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        exit;
    }

    /** @param array<string, string> $headers */
    public static function error(string $code, string $message, int $status, array $headers = []): never
    {
        self::json(['error' => $code, 'message' => $message], $status, $headers);
    }
}
