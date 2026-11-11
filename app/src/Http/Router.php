<?php

declare(strict_types=1);

namespace Dispatch\Http;

/** Pfad-Router mit den Platzhaltern `{id}` (Ziffern, kommt als int) und `{slug}` (a–z, 0–9, Bindestrich). */
final class Router
{
    private const PLACEHOLDERS = [
        '{id}' => '(?P<id>[0-9]+)',
        '{slug}' => '(?P<slug>[a-z0-9-]+)',
    ];

    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    /** @param callable(array<string, int|string>): mixed $handler */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . strtr(preg_quote($pattern, '#'), $this->escapedPlaceholders()) . '$#';
        $this->routes[] = ['method' => strtoupper($method), 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(Request $request): never
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path(), $matches) !== 1) {
                continue;
            }
            if ($route['method'] === $request->method()) {
                ($route['handler'])($this->parameters($matches));
                Response::empty(204);
            }
            $allowed[] = $route['method'];
        }

        if ($allowed !== []) {
            Response::error('method_not_allowed', 'Methode nicht erlaubt.', 405, ['Allow' => implode(', ', array_unique($allowed))]);
        }

        Response::error('not_found', 'Diesen Pfad gibt es hier nicht.', 404);
    }

    /** @return array<string, string> */
    private function escapedPlaceholders(): array
    {
        $escaped = [];
        foreach (self::PLACEHOLDERS as $placeholder => $group) {
            $escaped[preg_quote($placeholder, '#')] = $group;
        }

        return $escaped;
    }

    /**
     * @param array<int|string, string> $matches
     * @return array<string, int|string>
     */
    private function parameters(array $matches): array
    {
        $parameters = [];
        foreach ($matches as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            $parameters[$name] = $name === 'id' ? (int) $value : $value;
        }

        return $parameters;
    }
}
