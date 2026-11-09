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
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    /** @param array<string, string> $headers */
    public static function error(string $code, string $message, int $status, array $headers = []): never
    {
        self::json(['error' => $code, 'message' => $message], $status, $headers);
    }
}
