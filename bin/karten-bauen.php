<?php

declare(strict_types=1);

/**
 * Erzeugt die Kartendateien app/data/karten/de.php und welt.php aus Natural-Earth-Geodaten.
 *
 * Quelle: Natural Earth (https://www.naturalearthdata.com), Vektordaten im GeoJSON-Format
 *   - de:   ne_10m_admin_1_states_provinces (Bundesländer, Feld iso_3166_2)
 *   - welt: ne_110m_admin_0_countries (Länder, Feld ISO_A2_EH)
 * Lizenz: gemeinfrei („All versions of Natural Earth raster + vector map data … are in the public domain“,
 * https://www.naturalearthdata.com/about/terms-of-use/, abgerufen 2026-11-13). Namensfelder stammen aus Wikidata (CC0).
 *
 * Einmalig ausgeführt, das Ergebnis ist eingecheckt; die Rohdaten liegen nicht im Repository.
 *
 * Aufruf: php bin/karten-bauen.php <ne_10m_admin_1_states_provinces.geojson> <ne_110m_admin_0_countries.geojson> [zielordner]
 *
 * Ergebnis je Datei: ['width' => int, 'height' => int, 'regions' => [Code => ['name' => string, 'd' => Pfad]]]
 * Die Pfade nutzen ein Raster mit höchstens 1000 Einheiten Kantenlänge, relative Koordinaten, ganze Zahlen.
 */

const GRID_SIZE = 1000;
const MAX_FILE_BYTES = 60000;
/** Teilflächen kleiner als dies (Rastereinheiten²) entfallen, sofern die Region noch eine größere hat. */
const MIN_PART_AREA = 3.0;
const SKIPPED_WORLD_CODES = ['AQ'];

/** @var array<string, array{tolerance: float}> */
const MAPS = [
    'de' => ['tolerance' => 0.4],
    'welt' => ['tolerance' => 0.6],
];

if ($argc < 3) {
    fwrite(STDERR, "Aufruf: php bin/karten-bauen.php <bundeslaender.geojson> <laender.geojson> [zielordner]\n");
    exit(1);
}
ini_set('memory_limit', '3G');

$targetDirectory = $argv[3] ?? dirname(__DIR__) . '/app/data/karten';
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true)) {
    fwrite(STDERR, "Zielordner lässt sich nicht anlegen: $targetDirectory\n");
    exit(1);
}

writeMap('de', collectGermany($argv[1]), $targetDirectory);
writeMap('welt', collectWorld($argv[2]), $targetDirectory);

/**
 * @return array<string, array{name: string, polygons: list<list<list<array{0: float, 1: float}>>>}>
 */
function collectGermany(string $file): array
{
    $regions = [];
    foreach (readFeatures($file) as $feature) {
        $properties = $feature['properties'];
        if (($properties['iso_a2'] ?? '') !== 'DE' || !str_starts_with((string) ($properties['iso_3166_2'] ?? ''), 'DE-')) {
            continue;
        }
        $regions[$properties['iso_3166_2']] = ['name' => (string) ($properties['name_de'] ?? $properties['name']), 'polygons' => polygonsOf($feature['geometry'])];
    }
    if (count($regions) !== 16) {
        fwrite(STDERR, 'Erwartet 16 Bundesländer, gefunden: ' . count($regions) . "\n");
        exit(1);
    }

    return $regions;
}

/**
 * @return array<string, array{name: string, polygons: list<list<list<array{0: float, 1: float}>>>}>
 */
function collectWorld(string $file): array
{
    $regions = [];
    foreach (readFeatures($file) as $feature) {
        $properties = $feature['properties'];
        $code = (string) ($properties['ISO_A2_EH'] ?? '-99');
        if (!preg_match('/^[A-Z]{2}$/', $code) || in_array($code, SKIPPED_WORLD_CODES, true)) {
            continue;
        }
        $regions[$code] = ['name' => (string) ($properties['NAME_DE'] ?? $properties['NAME']), 'polygons' => polygonsOf($feature['geometry'])];
    }

    return $regions;
}

/** @return list<array{properties: array<string, mixed>, geometry: array<string, mixed>}> */
function readFeatures(string $file): array
{
    $json = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    return $json['features'];
}

/** @return list<list<list<array{0: float, 1: float}>>> Polygone → Ringe → Punkte (Länge, Breite) */
function polygonsOf(array $geometry): array
{
    return $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
}

/**
 * @param array<string, array{name: string, polygons: list<list<list<array{0: float, 1: float}>>>}> $regions
 */
function writeMap(string $name, array $regions, string $targetDirectory): void
{
    $tolerance = MAPS[$name]['tolerance'];
    [$minLongitude, $maxLongitude, $minLatitude, $maxLatitude] = boundsOf($regions);
    // Längentreue Breite: die Länge wird mit dem Kosinus der mittleren Breite gestaucht, damit Deutschland nicht verzerrt wirkt.
    $stretch = $name === 'de' ? cos(deg2rad(($minLatitude + $maxLatitude) / 2)) : 1.0;
    $spanX = ($maxLongitude - $minLongitude) * $stretch;
    $spanY = $maxLatitude - $minLatitude;
    $scale = GRID_SIZE / max($spanX, $spanY);
    $width = (int) ceil($spanX * $scale);
    $height = (int) ceil($spanY * $scale);

    $output = [];
    foreach ($regions as $code => $region) {
        $parts = [];
        foreach ($region['polygons'] as $polygon) {
            $rings = [];
            foreach ($polygon as $ring) {
                $points = [];
                foreach ($ring as [$longitude, $latitude]) {
                    $points[] = [($longitude - $minLongitude) * $stretch * $scale, ($maxLatitude - $latitude) * $scale];
                }
                $simplified = simplifyRing($points, $tolerance);
                if (count($simplified) >= 4) {
                    $rings[] = $simplified;
                }
            }
            if ($rings !== []) {
                $parts[] = ['rings' => $rings, 'area' => abs(ringArea($rings[0]))];
            }
        }
        usort($parts, fn (array $first, array $second): int => $second['area'] <=> $first['area']);
        $kept = array_values(array_filter($parts, fn (array $part): bool => $part['area'] >= MIN_PART_AREA));
        if ($kept === [] && $parts !== []) {
            $kept = [$parts[0]];
        }
        if ($kept === []) {
            continue;
        }
        $path = '';
        foreach ($kept as $part) {
            foreach ($part['rings'] as $ring) {
                $path .= relativePath($ring);
            }
        }
        $output[$code] = ['name' => $region['name'], 'd' => $path];
    }
    ksort($output);

    $source = "<?php\n\n// Erzeugt von bin/karten-bauen.php aus Natural Earth (gemeinfrei). Nicht von Hand ändern.\n\nreturn "
        . var_export(['width' => $width, 'height' => $height, 'regions' => $output], true) . ";\n";
    $source = (string) preg_replace('/=>\s*\n\s*array \(/', '=> array (', $source);
    $bytes = strlen($source);
    file_put_contents($targetDirectory . '/' . $name . '.php', $source);
    fwrite(STDOUT, sprintf("%s.php: %d Regionen, %d x %d, %d Byte%s\n", $name, count($output), $width, $height, $bytes, $bytes > MAX_FILE_BYTES ? ' — ZU GROSS' : ''));
    if ($bytes > MAX_FILE_BYTES) {
        exit(2);
    }
}

/**
 * @param array<string, array{name: string, polygons: list<list<list<array{0: float, 1: float}>>>}> $regions
 * @return array{0: float, 1: float, 2: float, 3: float}
 */
function boundsOf(array $regions): array
{
    $minLongitude = $minLatitude = INF;
    $maxLongitude = $maxLatitude = -INF;
    foreach ($regions as $region) {
        foreach ($region['polygons'] as $polygon) {
            foreach ($polygon[0] as [$longitude, $latitude]) {
                $minLongitude = min($minLongitude, $longitude);
                $maxLongitude = max($maxLongitude, $longitude);
                $minLatitude = min($minLatitude, $latitude);
                $maxLatitude = max($maxLatitude, $latitude);
            }
        }
    }

    return [$minLongitude, $maxLongitude, $minLatitude, $maxLatitude];
}

/**
 * Douglas-Peucker auf einem geschlossenen Ring; der Ring wird an seinem entferntesten Punkt in zwei Ketten geteilt.
 *
 * @param list<array{0: float, 1: float}> $points
 * @return list<array{0: int, 1: int}> geschlossen (erster = letzter Punkt), auf das Raster gerundet
 */
function simplifyRing(array $points, float $tolerance): array
{
    array_pop($points);
    $count = count($points);
    if ($count < 3) {
        return [];
    }
    $farthest = 0;
    $farthestDistance = -1.0;
    for ($index = 1; $index < $count; $index++) {
        $distance = hypot($points[$index][0] - $points[0][0], $points[$index][1] - $points[0][1]);
        if ($distance > $farthestDistance) {
            $farthestDistance = $distance;
            $farthest = $index;
        }
    }
    $firstChain = simplifyChain(array_slice($points, 0, $farthest + 1), $tolerance);
    $secondChain = simplifyChain(array_merge(array_slice($points, $farthest), [$points[0]]), $tolerance);
    $merged = array_merge($firstChain, array_slice($secondChain, 1));

    $rounded = [];
    foreach ($merged as [$x, $y]) {
        $point = [(int) round($x), (int) round($y)];
        if ($rounded === [] || $rounded[count($rounded) - 1] !== $point) {
            $rounded[] = $point;
        }
    }
    if ($rounded !== [] && $rounded[0] !== $rounded[count($rounded) - 1]) {
        $rounded[] = $rounded[0];
    }

    return $rounded;
}

/**
 * @param list<array{0: float, 1: float}> $chain
 * @return list<array{0: float, 1: float}>
 */
function simplifyChain(array $chain, float $tolerance): array
{
    $count = count($chain);
    if ($count < 3) {
        return $chain;
    }
    $start = $chain[0];
    $end = $chain[$count - 1];
    $worstDistance = 0.0;
    $worstIndex = 0;
    for ($index = 1; $index < $count - 1; $index++) {
        $distance = distanceToSegment($chain[$index], $start, $end);
        if ($distance > $worstDistance) {
            $worstDistance = $distance;
            $worstIndex = $index;
        }
    }
    if ($worstDistance <= $tolerance) {
        return [$start, $end];
    }
    $left = simplifyChain(array_slice($chain, 0, $worstIndex + 1), $tolerance);
    $right = simplifyChain(array_slice($chain, $worstIndex), $tolerance);

    return array_merge($left, array_slice($right, 1));
}

/**
 * @param array{0: float, 1: float} $point
 * @param array{0: float, 1: float} $start
 * @param array{0: float, 1: float} $end
 */
function distanceToSegment(array $point, array $start, array $end): float
{
    $deltaX = $end[0] - $start[0];
    $deltaY = $end[1] - $start[1];
    $lengthSquared = $deltaX * $deltaX + $deltaY * $deltaY;
    if ($lengthSquared === 0.0) {
        return hypot($point[0] - $start[0], $point[1] - $start[1]);
    }
    $position = max(0.0, min(1.0, (($point[0] - $start[0]) * $deltaX + ($point[1] - $start[1]) * $deltaY) / $lengthSquared));

    return hypot($point[0] - ($start[0] + $position * $deltaX), $point[1] - ($start[1] + $position * $deltaY));
}

/** @param list<array{0: int, 1: int}> $ring */
function ringArea(array $ring): float
{
    $sum = 0.0;
    for ($index = 0, $last = count($ring) - 1; $index < $last; $index++) {
        $sum += $ring[$index][0] * $ring[$index + 1][1] - $ring[$index + 1][0] * $ring[$index][1];
    }

    return $sum / 2;
}

/**
 * Pfad mit absolutem Startpunkt und relativen Schritten, geschlossen: „M x y l dx dy dx dy z“ (zeichnet ohne den doppelten Schlusspunkt).
 *
 * @param list<array{0: int, 1: int}> $ring
 */
function relativePath(array $ring): string
{
    array_pop($ring);
    $path = 'M' . $ring[0][0] . ' ' . $ring[0][1] . 'l';
    $previous = $ring[0];
    foreach (array_slice($ring, 1) as $point) {
        $path .= ($point[0] - $previous[0]) . ' ' . ($point[1] - $previous[1]) . ' ';
        $previous = $point;
    }

    return rtrim($path) . 'z';
}
