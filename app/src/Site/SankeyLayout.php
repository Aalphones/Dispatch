<?php

declare(strict_types=1);

namespace Dispatch\Site;

/**
 * Rechnet die Geometrie eines Sankey-Diagramms vor: Spalten nach längstem Pfad, Knotenhöhe = größerer Wert aus Zufluss
 * und Abfluss, Bänder als kubische Kurven. Reine Rechnung ohne DOM; das Ergebnis zeichnet {@see ChartComplexPlots}.
 */
final class SankeyLayout
{
    public const MAX_COLUMNS = 5;
    public const NODE_WIDTH = 10.0;
    public const NODE_GAP = 8.0;

    private function __construct()
    {
    }

    /**
     * Spalte jedes Knotens = Länge des längsten Wegs von einer Quelle bis zu ihm. Voraussetzung: keine Kreisläufe.
     *
     * @param list<array{from: string, to: string, value: float|null}> $links
     * @return array<string, int>
     */
    public static function columns(array $links): array
    {
        $predecessors = [];
        $nodes = [];
        foreach ($links as $link) {
            $predecessors[$link['to']][] = $link['from'];
            $nodes[$link['from']] = true;
            $nodes[$link['to']] = true;
        }

        $columns = [];
        $columnOf = function (string $node) use (&$columnOf, &$columns, $predecessors): int {
            if (isset($columns[$node])) {
                return $columns[$node];
            }
            $column = 0;
            foreach ($predecessors[$node] ?? [] as $predecessor) {
                $column = max($column, $columnOf($predecessor) + 1);
            }

            return $columns[$node] = $column;
        };
        foreach (array_keys($nodes) as $node) {
            $columnOf((string) $node);
        }

        return $columns;
    }

    /**
     * @param list<array{from: string, to: string, value: float|null}> $links
     * @return array{
     *     nodes: list<array{name: string, column: int, x: float, y: float, height: float, value: float}>,
     *     links: list<array{from: int, to: int, value: float, startY: float, endY: float, width: float}>,
     *     columnCount: int
     * } Knoten in der Reihenfolge ihres ersten Auftretens; Bänder verweisen auf Knotenindizes
     */
    public static function layout(array $links, float $width, float $height, float $leftMargin, float $rightMargin): array
    {
        $links = array_values(array_filter($links, fn (array $link): bool => $link['value'] > 0));
        $columnOf = self::columns($links);
        $columnCount = $columnOf === [] ? 0 : max($columnOf) + 1;

        $index = [];
        $nodes = [];
        $inflow = [];
        $outflow = [];
        foreach ($links as $link) {
            foreach ([$link['from'], $link['to']] as $name) {
                if (!isset($index[$name])) {
                    $index[$name] = count($nodes);
                    $nodes[] = ['name' => $name, 'column' => $columnOf[$name], 'x' => 0.0, 'y' => 0.0, 'height' => 0.0, 'value' => 0.0];
                    $inflow[$index[$name]] = 0.0;
                    $outflow[$index[$name]] = 0.0;
                }
            }
            $outflow[$index[$link['from']]] += $link['value'];
            $inflow[$index[$link['to']]] += $link['value'];
        }
        foreach ($nodes as $position => $node) {
            $nodes[$position]['value'] = max($inflow[$position], $outflow[$position]);
        }

        $scale = self::scale($nodes, $columnCount, $height);
        $columnX = self::columnPositions($columnCount, $width, $leftMargin, $rightMargin);
        $cursor = array_fill(0, $columnCount, 0.0);
        foreach ($nodes as $position => $node) {
            $nodes[$position]['x'] = $columnX[$node['column']];
            $nodes[$position]['y'] = $cursor[$node['column']];
            $nodes[$position]['height'] = $node['value'] * $scale;
            $cursor[$node['column']] += $nodes[$position]['height'] + self::NODE_GAP;
        }

        return ['nodes' => $nodes, 'links' => self::placeLinks($links, $index, $nodes, $scale), 'columnCount' => $columnCount];
    }

    /** Eine gemeinsame Skala, damit gleich breite Bänder gleich viel bedeuten; die vollste Spalte füllt die Höhe. */
    private static function scale(array $nodes, int $columnCount, float $height): float
    {
        $scale = INF;
        for ($column = 0; $column < $columnCount; $column++) {
            $sum = 0.0;
            $count = 0;
            foreach ($nodes as $node) {
                if ($node['column'] === $column) {
                    $sum += $node['value'];
                    $count++;
                }
            }
            $scale = min($scale, ($height - ($count - 1) * self::NODE_GAP) / $sum);
        }

        return $scale;
    }

    /** @return list<float> linke Kante der Knoten je Spalte */
    private static function columnPositions(int $columnCount, float $width, float $leftMargin, float $rightMargin): array
    {
        $span = $width - $leftMargin - $rightMargin - self::NODE_WIDTH;
        $positions = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $positions[] = $leftMargin + ($columnCount > 1 ? $span * $column / ($columnCount - 1) : 0.0);
        }

        return $positions;
    }

    /**
     * Bänder laufen am Knoten nach der Höhe ihres Gegenübers geordnet, damit sich möglichst wenige kreuzen.
     *
     * @param list<array{from: string, to: string, value: float|null}> $links
     * @param array<string, int> $index
     * @return list<array{from: int, to: int, value: float, startY: float, endY: float, width: float}>
     */
    private static function placeLinks(array $links, array $index, array $nodes, float $scale): array
    {
        $ordered = array_map(fn (array $link): array => ['from' => $index[$link['from']], 'to' => $index[$link['to']], 'value' => (float) $link['value']], $links);

        $startOffset = array_fill(0, count($nodes), 0.0);
        usort($ordered, fn (array $first, array $second): int => [$nodes[$first['to']]['y'], $first['to']] <=> [$nodes[$second['to']]['y'], $second['to']]);
        foreach ($ordered as $position => $link) {
            $ordered[$position]['startY'] = $nodes[$link['from']]['y'] + $startOffset[$link['from']] + $link['value'] * $scale / 2;
            $startOffset[$link['from']] += $link['value'] * $scale;
        }

        $endOffset = array_fill(0, count($nodes), 0.0);
        $byOrigin = array_keys($ordered);
        usort($byOrigin, fn (int $first, int $second): int => [$nodes[$ordered[$first]['from']]['y'], $ordered[$first]['from']] <=> [$nodes[$ordered[$second]['from']]['y'], $ordered[$second]['from']]);
        foreach ($byOrigin as $position) {
            $link = $ordered[$position];
            $ordered[$position]['endY'] = $nodes[$link['to']]['y'] + $endOffset[$link['to']] + $link['value'] * $scale / 2;
            $endOffset[$link['to']] += $link['value'] * $scale;
            $ordered[$position]['width'] = $link['value'] * $scale;
        }

        return $ordered;
    }
}
