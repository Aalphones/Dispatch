<?php

declare(strict_types=1);

namespace Dispatch\Http;

use Dispatch\Domain\DomainError;

final class Request
{
    private const BEARER_PREFIX = 'Bearer ';

    private ?string $body = null;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $form $_POST (multipart/form-data oder urlencoded)
     * @param array<string, mixed> $files $_FILES
     */
    private function __construct(
        private readonly array $server,
        private readonly array $form = [],
        private readonly array $files = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function form(): array
    {
        return $this->form;
    }

    /** @return array<string, mixed> Rohform von $_FILES */
    public function files(): array
    {
        return $this->files;
    }

    /** Länge laut Kopfzeile; bei überschrittenem post_max_size bleiben $_POST und $_FILES leer. */
    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }

    /** php://input lässt sich nur einmal lesen, deshalb wird der Body gemerkt. */
    public function body(): string
    {
        if ($this->body === null) {
            $this->body = (string) file_get_contents('php://input');
        }

        return $this->body;
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER, $_POST, $_FILES);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $path = parse_url((string) ($this->server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    public function query(string $name): ?string
    {
        parse_str((string) ($this->server['QUERY_STRING'] ?? ''), $parameters);
        $value = $parameters[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function cookie(string $name): ?string
    {
        $header = $this->server['HTTP_COOKIE'] ?? null;
        if (!is_string($header)) {
            return null;
        }

        foreach (explode(';', $header) as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2 && $parts[0] === $name) {
                return urldecode($parts[1]);
            }
        }

        return null;
    }

    /** Nie Weiterleitungs-Header: Strato steht nicht hinter einem eigenen Proxy, X-Forwarded-For wäre frei fälschbar. */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    /** @return array<string, mixed> Body als JSON-Objekt */
    public function json(): array
    {
        $decoded = json_decode($this->body(), true);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new DomainError('invalid_json', 'Der Body muss ein JSON-Objekt sein.');
        }

        return $decoded;
    }

    public function bearerToken(): ?string
    {
        // Strato reicht den Header je nach Aufrufweg als HTTP_ oder REDIRECT_HTTP_ durch.
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = $this->server[$key] ?? null;
            if (is_string($value) && str_starts_with($value, self::BEARER_PREFIX)) {
                return substr($value, strlen(self::BEARER_PREFIX));
            }
        }

        return null;
    }
}
