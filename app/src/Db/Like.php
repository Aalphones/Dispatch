<?php

declare(strict_types=1);

namespace Dispatch\Db;

/** Baut LIKE-Muster, in denen `%` und `_` des Suchworts wörtlich gelten. */
final class Like
{
    /** Passt zu `ESCAPE '!'` in der Abfrage; ein Backslash als Escape hängt am SQL-Modus des Servers. */
    public const ESCAPE_CLAUSE = "ESCAPE '!'";

    public static function contains(string $term): string
    {
        return '%' . self::escape($term) . '%';
    }

    public static function startsWith(string $prefix): string
    {
        return self::escape($prefix) . '%';
    }

    private static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
