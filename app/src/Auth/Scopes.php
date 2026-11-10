<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Domain\DomainError;

/** Zugriffsbereiche eines MCP-Tokens. Keiner schließt einen anderen ein. */
final class Scopes
{
    public const READ = 'blog:read';
    public const WRITE = 'blog:write';
    public const ADMIN = 'blog:admin';
    public const ALL = [self::READ, self::WRITE, self::ADMIN];

    /**
     * Trennt an Leerzeichen und Kommas, entfernt Doppelte und sortiert in der Reihenfolge von ALL.
     *
     * @param string|list<string> $value
     * @return list<string>
     */
    public static function parse(string|array $value): array
    {
        $parts = is_array($value) ? $value : [$value];
        $requested = [];
        foreach ($parts as $part) {
            $pieces = preg_split('/[\s,]+/', (string) $part, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($pieces === false ? [] : $pieces as $scope) {
                $requested[] = $scope;
            }
        }

        $validList = implode(', ', self::ALL);
        if ($requested === []) {
            throw new DomainError('invalid_value', 'Mindestens ein Scope ist nötig. Gültig: ' . $validList . '.', ['field' => 'scopes']);
        }

        $unknown = array_values(array_unique(array_diff($requested, self::ALL)));
        if ($unknown !== []) {
            throw new DomainError(
                'invalid_value',
                sprintf('Unbekannter Scope „%s“. Gültig: %s.', implode(', ', $unknown), $validList),
                ['field' => 'scopes', 'unknown' => $unknown]
            );
        }

        return array_values(array_intersect(self::ALL, $requested));
    }
}
