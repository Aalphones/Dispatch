<?php

declare(strict_types=1);

namespace Dispatch\Site;

/**
 * Liest die vorgerechneten Kartendateien (app/data/karten, erzeugt von bin/karten-bauen.php aus Natural Earth).
 */
final class ChartMaps
{
    private const DIRECTORY = __DIR__ . '/../../data/karten/';

    /** @var array<string, array{width: int, height: int, regions: array<string, array{name: string, d: string}>}|null> */
    private static array $cache = [];

    private function __construct()
    {
    }

    /** @return array{width: int, height: int, regions: array<string, array{name: string, d: string}>}|null */
    public static function load(string $map): ?array
    {
        if (!array_key_exists($map, self::$cache)) {
            $file = self::DIRECTORY . $map . '.php';
            self::$cache[$map] = preg_match('/^[a-z]+$/', $map) === 1 && is_file($file) ? require $file : null;
        }

        return self::$cache[$map];
    }

    /** @return list<string> Regionscodes, die die Karte zeichnen kann */
    public static function codes(string $map): array
    {
        $data = self::load($map);

        return $data === null ? [] : array_keys($data['regions']);
    }
}
