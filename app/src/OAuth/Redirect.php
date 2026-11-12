<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

/** Baut Weiterleitungs-Adressen; vorhandene Query-Parameter der Rückadresse bleiben erhalten. */
final class Redirect
{
    /** @param array<string, string|null> $params Einträge mit `null` entfallen */
    public static function with(string $uri, array $params): string
    {
        $query = http_build_query(array_filter($params, static fn (?string $value): bool => $value !== null), '', '&', PHP_QUERY_RFC3986);
        if ($query === '') {
            return $uri;
        }

        return $uri . (str_contains($uri, '?') ? '&' : '?') . $query;
    }
}
