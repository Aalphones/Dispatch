<?php

declare(strict_types=1);

namespace Dispatch\Site;

/**
 * Squarified-Treemap-Layout (Bruls, Huizing, van Wijk): teilt ein Rechteck in Flächen, die möglichst quadratisch bleiben.
 * Reine Rechnung ohne DOM.
 */
final class TreemapLayout
{
    private function __construct()
    {
    }

    /**
     * @param list<float> $values positive Werte, absteigend sortiert
     * @return list<array{x: float, y: float, width: float, height: float}> je Wert ein Rechteck, gleiche Reihenfolge
     */
    public static function squarify(array $values, float $x, float $y, float $width, float $height): array
    {
        $total = array_sum($values);
        if ($values === [] || $total <= 0.0) {
            return [];
        }
        $areas = array_map(fn (float $value): float => $value * $width * $height / $total, $values);

        $rectangles = [];
        $row = [];
        $queue = array_keys($areas);
        while ($queue !== []) {
            $side = min($width, $height);
            $next = $queue[0];
            if ($row === [] || self::worstRatio([...$row, $next], $areas, $side) <= self::worstRatio($row, $areas, $side)) {
                $row[] = $next;
                array_shift($queue);
                continue;
            }
            self::placeRow($row, $areas, $x, $y, $width, $height, $rectangles);
            $row = [];
        }
        self::placeRow($row, $areas, $x, $y, $width, $height, $rectangles);
        ksort($rectangles);

        return array_values($rectangles);
    }

    /**
     * @param list<int> $row
     * @param list<float> $areas
     */
    private static function worstRatio(array $row, array $areas, float $side): float
    {
        $sum = 0.0;
        $largest = 0.0;
        $smallest = INF;
        foreach ($row as $index) {
            $sum += $areas[$index];
            $largest = max($largest, $areas[$index]);
            $smallest = min($smallest, $areas[$index]);
        }

        return max($side * $side * $largest / ($sum * $sum), $sum * $sum / ($side * $side * $smallest));
    }

    /**
     * Legt die Reihe an die kürzere Seite und verkleinert das Restrechteck.
     *
     * @param list<int> $row
     * @param list<float> $areas
     * @param array<int, array{x: float, y: float, width: float, height: float}> $rectangles
     */
    private static function placeRow(array $row, array $areas, float &$x, float &$y, float &$width, float &$height, array &$rectangles): void
    {
        if ($row === []) {
            return;
        }
        $sum = 0.0;
        foreach ($row as $index) {
            $sum += $areas[$index];
        }

        if ($width >= $height) {
            $rowWidth = $sum / $height;
            $cursor = $y;
            foreach ($row as $index) {
                $rectangles[$index] = ['x' => $x, 'y' => $cursor, 'width' => $rowWidth, 'height' => $areas[$index] / $rowWidth];
                $cursor += $areas[$index] / $rowWidth;
            }
            $x += $rowWidth;
            $width -= $rowWidth;

            return;
        }

        $rowHeight = $sum / $width;
        $cursor = $x;
        foreach ($row as $index) {
            $rectangles[$index] = ['x' => $cursor, 'y' => $y, 'width' => $areas[$index] / $rowHeight, 'height' => $rowHeight];
            $cursor += $areas[$index] / $rowHeight;
        }
        $y += $rowHeight;
        $height -= $rowHeight;
    }
}
