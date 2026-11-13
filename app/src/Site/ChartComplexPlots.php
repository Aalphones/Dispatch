<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dom\Element;

/**
 * Zeichnet die vier Typen mit eigener Layout-Rechnung: Sankey, Baumkarte, Blasen und Karte.
 *
 * Alle Geometrie entsteht hier auf dem Server; das Skript der Interaktion (Phase 8) schaltet nur Attribute und Klassen
 * um und rechnet nie neu. Hervorhebbare Teile tragen daher feste Kennungen: Sankey `data-knoten`/`data-von`/`data-nach`
 * (Kennung = `k` + laufende Nummer), Baumkarte `data-gruppe` (`g` + Nummer), Karte `data-region` (Regionscode).
 * Farben liegen in diagramme.css (r1…r8, s1…s5), nie als style-Attribut.
 */
final class ChartComplexPlots
{
    private const WIDTH = 440.0;
    private const MAX_SERIES_COLORS = 8;

    private const SANKEY_TOP = 6.0;
    private const SANKEY_BOTTOM = 10.0;
    private const SANKEY_MIN_HEIGHT = 160.0;
    private const SANKEY_HEIGHT_PER_NODE = 38.0;
    private const SANKEY_MIN_MARGIN = 40.0;
    private const SANKEY_MAX_MARGIN = 120.0;
    private const LABEL_CHARACTER_WIDTH = 6.6;

    private const TREEMAP_HEIGHT = 280.0;
    private const TILE_GAP = 2.0;
    private const TILE_RADIUS = 3.0;
    /** Mindestmaße einer Kachel für Name und Wert, nur Name, bzw. gekürzten Namen. */
    private const TILE_LABEL_MIN_HEIGHT = 34.0;
    private const TILE_LABEL_PADDING = 14.0;
    private const TILE_VALUE_MIN_HEIGHT = 50.0;
    private const TILE_SHORT_MIN_WIDTH = 26.0;
    private const TILE_SHORT_MIN_HEIGHT = 18.0;
    private const TILE_SHORT_CHARACTER_WIDTH = 6.2;

    private const BUBBLE_HEIGHT = 290.0;
    private const BUBBLE_TOP = 22.0;
    private const BUBBLE_BOTTOM = 252.0;
    private const BUBBLE_RIGHT = 424.0;
    private const BUBBLE_MAX_RADIUS = 22.0;
    private const BUBBLE_MIN_RADIUS = 2.5;
    private const BUBBLE_PADDING = 12.0;
    private const BUBBLE_LABELED = 5;

    private const MAP_BOX = 440.0;
    private const MAP_CLASSES = 5;

    public function __construct(private readonly ChartShapes $shapes)
    {
    }

    /* ---------------------------------------------------------------- Sankey */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function sankey(array $data, string $unit): ?array
    {
        $links = $data['links'];
        $columnOf = SankeyLayout::columns($links);
        $columnCount = $columnOf === [] ? 0 : max($columnOf) + 1;
        $namesIn = fn (int $column): array => array_keys(array_filter($columnOf, fn (int $value): bool => $value === $column));
        $leftMargin = $this->labelMargin($namesIn(0));
        $rightMargin = $this->labelMargin($namesIn($columnCount - 1));

        $columnSizes = array_count_values($columnOf);
        $innerHeight = max(self::SANKEY_MIN_HEIGHT, max($columnSizes ?: [0]) * self::SANKEY_HEIGHT_PER_NODE);
        $layout = SankeyLayout::layout($links, self::WIDTH, $innerHeight, $leftMargin, $rightMargin);
        if ($layout['nodes'] === []) {
            return null;
        }

        $decimals = ChartFormat::decimalsOf(array_column($links, 'value'));
        $rootColor = $this->rootColors($layout['nodes']);
        $shapes = [];
        foreach ($layout['links'] as $link) {
            $from = $layout['nodes'][$link['from']];
            $to = $layout['nodes'][$link['to']];
            $startX = $from['x'] + SankeyLayout::NODE_WIDTH;
            $middleX = ($startX + $to['x']) / 2;
            $startY = self::SANKEY_TOP + $link['startY'];
            $endY = self::SANKEY_TOP + $link['endY'];
            $path = $this->shapes->svg('path', [
                'class' => 'band',
                'data-von' => 'k' . ($link['from'] + 1),
                'data-nach' => 'k' . ($link['to'] + 1),
                'd' => sprintf('M%s %sC%s %s %s %s %s %s', $this->c($startX), $this->c($startY), $this->c($middleX), $this->c($startY), $this->c($middleX), $this->c($endY), $this->c($to['x']), $this->c($endY)),
                'stroke-width' => $this->c($link['width']),
            ]);
            $this->shapes->withTooltip($path, $from['name'] . ' → ' . $to['name'] . ': ' . ChartFormat::withUnit($link['value'], $decimals, $unit));
            $shapes[] = $this->shapes->group($rootColor[$link['from']], [$path]);
        }

        foreach ($layout['nodes'] as $index => $node) {
            $shapes[] = $this->sankeyNode($index, $node, $columnCount, $rootColor[$index], $leftMargin, $rightMargin, $decimals, $unit);
        }

        return $this->shapes->plot(self::WIDTH, self::SANKEY_TOP + $innerHeight + self::SANKEY_BOTTOM, $shapes, $this->summarizeSankey($layout, $decimals, $unit));
    }

    /**
     * @param array{name: string, column: int, x: float, y: float, height: float, value: float} $node
     */
    private function sankeyNode(int $index, array $node, int $columnCount, string $color, float $leftMargin, float $rightMargin, int $decimals, string $unit): Element
    {
        $top = self::SANKEY_TOP + $node['y'];
        $box = $this->shapes->box($node['x'], $top, SankeyLayout::NODE_WIDTH, max($node['height'], 1.0), $node['name'] . ': ' . ChartFormat::withUnit($node['value'], $decimals, $unit));
        $middle = $top + $node['height'] / 2 + 4;
        $isFirst = $node['column'] === 0;
        $isLast = $node['column'] === $columnCount - 1;

        if ($isFirst) {
            $label = $this->shapes->label($node['x'] - 6, $middle, ChartFormat::truncate($node['name'], $this->charactersIn($leftMargin - 6)), 'wert', 'end');
        } else {
            $label = $this->shapes->label($node['x'] + SankeyLayout::NODE_WIDTH + 6, $middle, ChartFormat::truncate($node['name'], $this->charactersIn($isLast ? $rightMargin - 6 : 110.0)), $isLast ? 'wert' : 'wert halo');
        }

        $group = $this->shapes->group('knoten ' . $color, [$box, $label]);
        $group->setAttribute('data-knoten', 'k' . ($index + 1));

        return $group;
    }

    /**
     * Quellknoten der ersten Spalte tragen r1…r8, alle übrigen Knoten und deren Bänder bleiben neutral.
     *
     * @param list<array{name: string, column: int}> $nodes
     * @return array<int, string>
     */
    private function rootColors(array $nodes): array
    {
        $colors = [];
        $rootIndex = 0;
        foreach ($nodes as $index => $node) {
            if ($node['column'] === 0 && $rootIndex < self::MAX_SERIES_COLORS) {
                $colors[$index] = 'r' . ($rootIndex + 1);
                $rootIndex++;
                continue;
            }
            $colors[$index] = 'neutral';
            if ($node['column'] === 0) {
                $rootIndex++;
            }
        }

        return $colors;
    }

    /** @param list<string> $names */
    private function labelMargin(array $names): float
    {
        $widest = 0.0;
        foreach ($names as $name) {
            $widest = max($widest, ChartFormat::textWidth($name));
        }

        return min(self::SANKEY_MAX_MARGIN, max(self::SANKEY_MIN_MARGIN, ceil($widest) + 12));
    }

    private function charactersIn(float $width): int
    {
        return max(3, (int) floor($width / self::LABEL_CHARACTER_WIDTH));
    }

    /** @param array<string, mixed> $layout */
    private function summarizeSankey(array $layout, int $decimals, string $unit): string
    {
        $largestLink = null;
        foreach ($layout['links'] as $link) {
            if ($largestLink === null || $link['value'] > $largestLink['value']) {
                $largestLink = $link;
            }
        }
        $largestNode = null;
        foreach ($layout['nodes'] as $node) {
            if ($largestNode === null || $node['value'] > $largestNode['value']) {
                $largestNode = $node;
            }
        }

        return sprintf(
            'Größter Fluss: %s → %s mit %s; größter Knoten: %s mit %s.',
            $layout['nodes'][$largestLink['from']]['name'],
            $layout['nodes'][$largestLink['to']]['name'],
            ChartFormat::withUnit($largestLink['value'], $decimals, $unit),
            $largestNode['name'],
            ChartFormat::withUnit($largestNode['value'], $decimals, $unit),
        );
    }

    /* ------------------------------------------------------------- Baumkarte */

    /**
     * Ebene 1 zeigt die Gruppen; jede Gruppe hat zusätzlich eine vorgerechnete, versteckte zweite Ebene mit ihren Untergruppen.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function treemap(array $data, string $unit): ?array
    {
        $groups = $this->groupLeaves($data['leaves']);
        if ($groups === []) {
            return null;
        }
        $decimals = ChartFormat::decimalsOf(array_column($data['leaves'], 'value'));
        $grandTotal = array_sum(array_column($groups, 'total'));
        $tiles = TreemapLayout::squarify(array_column($groups, 'total'), 0, 0, self::WIDTH, self::TREEMAP_HEIGHT);

        $overview = $this->shapes->group('ebene-1');
        $details = [];
        foreach ($groups as $index => $group) {
            $color = 'r' . min($index + 1, self::MAX_SERIES_COLORS);
            $identifier = 'g' . ($index + 1);
            $share = (int) round($group['total'] / $grandTotal * 100);
            $tooltip = $group['name'] . ': ' . ChartFormat::withUnit($group['total'], $decimals, $unit) . ' (' . $share . ' %)';
            $tile = $this->tile($tiles[$index], $tooltip, $group['name'], ChartFormat::withUnit($group['total'], $decimals, $unit));
            $tileGroup = $this->shapes->group('gruppe ' . $color, $tile);
            $tileGroup->setAttribute('data-gruppe', $identifier);
            $overview->appendChild($tileGroup);
            $details[] = $this->detailLevel($group, $color, $identifier, $decimals, $unit);
        }

        $largest = $groups[0];
        $summary = sprintf('Größter Bereich: %s mit %s von insgesamt %s.', $largest['name'], ChartFormat::withUnit($largest['total'], $decimals, $unit), ChartFormat::withUnit($grandTotal, $decimals, $unit));

        return $this->shapes->plot(self::WIDTH, self::TREEMAP_HEIGHT, [$overview, ...$details], $summary);
    }

    /**
     * @param list<array{group: string, label: string, value: float|null}> $leaves
     * @return list<array{name: string, total: float, kids: list<array{label: string, value: float}>}> Gruppen und Posten absteigend nach Wert
     */
    private function groupLeaves(array $leaves): array
    {
        $groups = [];
        foreach ($leaves as $leaf) {
            if ($leaf['value'] <= 0) {
                continue;
            }
            $groups[$leaf['group']] ??= ['name' => $leaf['group'], 'total' => 0.0, 'kids' => []];
            $groups[$leaf['group']]['total'] += $leaf['value'];
            $groups[$leaf['group']]['kids'][] = ['label' => $leaf['label'], 'value' => $leaf['value']];
        }
        $groups = array_values($groups);
        usort($groups, fn (array $first, array $second): int => $second['total'] <=> $first['total']);
        foreach ($groups as $index => $group) {
            usort($group['kids'], fn (array $first, array $second): int => $second['value'] <=> $first['value']);
            $groups[$index] = $group;
        }

        return $groups;
    }

    /** @param array{name: string, total: float, kids: list<array{label: string, value: float}>} $group */
    private function detailLevel(array $group, string $color, string $identifier, int $decimals, string $unit): Element
    {
        $rectangles = TreemapLayout::squarify(array_column($group['kids'], 'value'), 0, 0, self::WIDTH, self::TREEMAP_HEIGHT);
        $tiles = [];
        foreach ($group['kids'] as $index => $kid) {
            $text = ChartFormat::withUnit($kid['value'], $decimals, $unit);
            $tiles[] = $this->shapes->group('', $this->tile($rectangles[$index], $group['name'] . ' › ' . $kid['label'] . ': ' . $text, $kid['label'], $text));
        }
        $level = $this->shapes->group('ebene ' . $color, $tiles);
        $level->setAttribute('data-gruppe', $identifier);
        $level->setAttribute('hidden', '');

        return $level;
    }

    /**
     * @param array{x: float, y: float, width: float, height: float} $area
     * @return list<Element>
     */
    private function tile(array $area, string $tooltip, string $name, string $valueText): array
    {
        $x = $area['x'] + self::TILE_GAP / 2;
        $y = $area['y'] + self::TILE_GAP / 2;
        $width = $area['width'] - self::TILE_GAP;
        $height = $area['height'] - self::TILE_GAP;
        $elements = [$this->shapes->box($x, $y, $width, $height, $tooltip, '', self::TILE_RADIUS)];

        if ($width > ChartFormat::textWidth($name) + self::TILE_LABEL_PADDING && $height > self::TILE_LABEL_MIN_HEIGHT) {
            $elements[] = $this->shapes->label($x + 7, $y + 18, $name, 'in');
            if ($height > self::TILE_VALUE_MIN_HEIGHT) {
                $elements[] = $this->shapes->label($x + 7, $y + 34, $valueText, 'in leicht');
            }
        } elseif ($width > self::TILE_SHORT_MIN_WIDTH && $height > self::TILE_SHORT_MIN_HEIGHT) {
            $characters = max(2, (int) floor(($width - 8) / self::TILE_SHORT_CHARACTER_WIDTH));
            $elements[] = $this->shapes->label($x + 5, $y + 14, ChartFormat::truncate($name, $characters), 'in klein');
        }

        return $elements;
    }

    /* ----------------------------------------------------------------- Blasen */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function bubbles(array $data): ?array
    {
        $points = $data['points'];
        [$xName, $yName, $sizeName] = $data['axes'] ?? ['x', 'y', 'Größe'];
        $categories = array_values(array_unique(array_column($points, 'category')));
        $largestSize = max(array_column($points, 'size'));
        if ($largestSize <= 0) {
            return null;
        }

        $xScale = ChartFormat::niceRange(min(array_column($points, 'x')), max(array_column($points, 'x')));
        $yScale = ChartFormat::niceRange(min(array_column($points, 'y')), max(array_column($points, 'y')));
        $left = max(34.0, ceil($this->widestTick($yScale)) + 12);
        $toX = fn (float $value): float => $left + self::BUBBLE_PADDING + ($value - $xScale['min']) / ($xScale['max'] - $xScale['min']) * (self::BUBBLE_RIGHT - $left - 2 * self::BUBBLE_PADDING);
        $toY = fn (float $value): float => self::BUBBLE_BOTTOM - self::BUBBLE_PADDING - ($value - $yScale['min']) / ($yScale['max'] - $yScale['min']) * (self::BUBBLE_BOTTOM - self::BUBBLE_TOP - 2 * self::BUBBLE_PADDING);

        $shapes = [$this->bubbleAxes($xScale, $yScale, $left, $toX, $toY, $xName, $yName)];

        usort($points, fn (array $first, array $second): int => $second['size'] <=> $first['size']);
        $decimals = [
            ChartFormat::decimalsOf(array_column($points, 'x')),
            ChartFormat::decimalsOf(array_column($points, 'y')),
            ChartFormat::decimalsOf(array_column($points, 'size')),
        ];
        foreach ($points as $point) {
            $radius = max(self::BUBBLE_MIN_RADIUS, self::BUBBLE_MAX_RADIUS * sqrt($point['size'] / $largestSize));
            $tooltip = sprintf(
                '%s (%s): %s %s, %s %s, %s %s',
                $point['name'],
                $point['category'],
                $xName,
                ChartFormat::number($point['x'], $decimals[0]),
                $yName,
                ChartFormat::number($point['y'], $decimals[1]),
                $sizeName,
                ChartFormat::number($point['size'], $decimals[2]),
            );
            $circle = $this->shapes->svg('circle', [
                'class' => 'm ring',
                'cx' => $this->c($toX($point['x'])),
                'cy' => $this->c($toY($point['y'])),
                'r' => $this->c($radius),
                'fill-opacity' => '.86',
            ]);
            $shapes[] = $this->shapes->group('r' . min(array_search($point['category'], $categories, true) + 1, self::MAX_SERIES_COLORS), [$this->shapes->withTooltip($circle, $tooltip)]);
        }
        foreach (array_slice($points, 0, self::BUBBLE_LABELED) as $point) {
            // Die Beschriftung gehört zur Kategorie-Gruppe, damit der Filter sie mit der Blase ausblendet.
            $label = $this->shapes->label($toX($point['x']), $toY($point['y']) + 4, $point['name'], 'wert halo', 'middle');
            $shapes[] = $this->shapes->group('r' . min(array_search($point['category'], $categories, true) + 1, self::MAX_SERIES_COLORS), [$label]);
        }

        $summary = sprintf('Größte Blase: %s (%s) mit %s %s.', $points[0]['name'], $points[0]['category'], $sizeName, ChartFormat::number($points[0]['size'], $decimals[2]));

        return $this->shapes->plot(self::WIDTH, self::BUBBLE_HEIGHT, $shapes, $summary, $this->shapes->seriesLegend($categories));
    }

    /**
     * @param array{min: float, max: float, step: float, decimals: int} $xScale
     * @param array{min: float, max: float, step: float, decimals: int} $yScale
     */
    private function bubbleAxes(array $xScale, array $yScale, float $left, \Closure $toX, \Closure $toY, string $xName, string $yName): Element
    {
        $axis = $this->shapes->group('achse');
        foreach ($this->ticks($xScale) as $value) {
            $x = $toX($value);
            $axis->appendChild($this->shapes->svg('line', ['class' => 'gitter', 'x1' => $this->c($x), 'x2' => $this->c($x), 'y1' => $this->c(self::BUBBLE_TOP), 'y2' => $this->c(self::BUBBLE_BOTTOM)]));
            $axis->appendChild($this->shapes->label($x, self::BUBBLE_BOTTOM + 16, ChartFormat::number($value, $xScale['decimals']), '', 'middle'));
        }
        foreach ($this->ticks($yScale) as $index => $value) {
            $y = $toY($value);
            $axis->appendChild($this->shapes->svg('line', ['class' => $index === 0 ? 'basis' : 'gitter', 'x1' => $this->c($left), 'x2' => $this->c(self::BUBBLE_RIGHT), 'y1' => $this->c($y), 'y2' => $this->c($y)]));
            $axis->appendChild($this->shapes->label($left - 6, $y + 4, ChartFormat::number($value, $yScale['decimals']), '', 'end'));
        }
        $axis->appendChild($this->shapes->label(0, 10, $yName, 'achsentitel'));
        $axis->appendChild($this->shapes->label(self::BUBBLE_RIGHT, self::BUBBLE_HEIGHT - 4, $xName, 'achsentitel', 'end'));

        return $axis;
    }

    /**
     * @param array{min: float, max: float, step: float} $scale
     * @return list<float>
     */
    private function ticks(array $scale): array
    {
        $ticks = [];
        $count = (int) round(($scale['max'] - $scale['min']) / $scale['step']);
        for ($index = 0; $index <= $count; $index++) {
            $ticks[] = round($scale['min'] + $index * $scale['step'], 6);
        }

        return $ticks;
    }

    /** @param array{min: float, max: float, step: float, decimals: int} $scale */
    private function widestTick(array $scale): float
    {
        $widest = 0.0;
        foreach ($this->ticks($scale) as $value) {
            $widest = max($widest, ChartFormat::textWidth(ChartFormat::number($value, $scale['decimals'])));
        }

        return $widest;
    }

    /* ------------------------------------------------------------------ Karte */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function map(array $data, string $unit): ?array
    {
        $map = ChartMaps::load($data['map']);
        if ($map === null) {
            return null;
        }
        $values = [];
        $names = [];
        foreach ($data['regions'] as $region) {
            $values[$region['code']] = $region['value'];
            $names[$region['code']] = $region['name'] !== '' ? $region['name'] : $map['regions'][$region['code']]['name'];
        }
        $decimals = ChartFormat::decimalsOf(array_values($values));
        $breaks = $this->quantileBreaks(array_values($values));

        $scale = min(self::MAP_BOX / $map['width'], self::MAP_BOX / $map['height']);
        $offsetX = (self::WIDTH - $map['width'] * $scale) / 2;
        $height = ceil($map['height'] * $scale) + 4;

        $regions = [];
        foreach ($map['regions'] as $code => $region) {
            $regions[] = $this->region($code, $region, $values, $names, $breaks, $decimals, $unit);
        }
        $surface = $this->shapes->group('', $regions);
        $surface->setAttribute('transform', sprintf('translate(%s %s) scale(%s)', $this->c($offsetX), '2', rtrim(rtrim(number_format($scale, 4, '.', ''), '0'), '.')));

        $legend = $this->mapLegend($values, $breaks, $decimals, count($values) < count($map['regions']));

        return $this->shapes->plot(self::WIDTH, $height, [$surface], $this->summarizeMap($values, $names, $map, $decimals, $unit), $legend, true);
    }

    /**
     * Obere Klassengrenzen nach Quantilen: jede der fünf Klassen erhält gleich viele Regionen (soweit Gleichstände es zulassen).
     *
     * @param list<float> $values
     * @return list<float> fünf obere Grenzen
     */
    private function quantileBreaks(array $values): array
    {
        sort($values);
        $count = count($values);
        $breaks = [];
        for ($class = 1; $class <= self::MAP_CLASSES; $class++) {
            $breaks[] = $values[max(0, (int) ceil($count * $class / self::MAP_CLASSES) - 1)];
        }

        return $breaks;
    }

    /** @param list<float> $breaks */
    private function classOf(float $value, array $breaks): int
    {
        foreach ($breaks as $index => $upper) {
            if ($value <= $upper) {
                return $index + 1;
            }
        }

        return self::MAP_CLASSES;
    }

    /**
     * @param array{name: string, d: string} $region
     * @param array<string, float> $values
     * @param array<string, string> $names
     * @param list<float> $breaks
     */
    private function region(string $code, array $region, array $values, array $names, array $breaks, int $decimals, string $unit): Element
    {
        $attributes = ['data-region' => $code, 'd' => $region['d']];
        if (!isset($values[$code])) {
            $path = $this->shapes->svg('path', ['class' => 'region-leer'] + $attributes);

            return $this->shapes->withTooltip($path, $region['name'] . ': keine Daten');
        }

        $path = $this->shapes->svg('path', ['class' => 'm region'] + $attributes);
        $this->shapes->withTooltip($path, $names[$code] . ': ' . ChartFormat::withUnit($values[$code], $decimals, $unit));

        return $this->shapes->group('s' . $this->classOf($values[$code], $breaks), [$path]);
    }

    /**
     * @param array<string, float> $values
     * @param list<float> $breaks
     * @return list<array{class: string, name: string}>
     */
    private function mapLegend(array $values, array $breaks, int $decimals, bool $hasGaps): array
    {
        $ranges = [];
        foreach ($values as $value) {
            $class = $this->classOf($value, $breaks);
            $ranges[$class] = [min($ranges[$class][0] ?? $value, $value), max($ranges[$class][1] ?? $value, $value)];
        }
        ksort($ranges);

        $legend = [];
        foreach ($ranges as $class => [$lowest, $highest]) {
            $text = ChartFormat::number($lowest, $decimals);
            $legend[] = ['class' => 's' . $class, 'name' => $lowest === $highest ? $text : $text . '–' . ChartFormat::number($highest, $decimals)];
        }
        if ($hasGaps) {
            $legend[] = ['class' => 'keine', 'name' => 'keine Daten'];
        }

        return $legend;
    }

    /**
     * @param array<string, float> $values
     * @param array<string, string> $names
     * @param array{width: int, height: int, regions: array<string, array{name: string, d: string}>} $map
     */
    private function summarizeMap(array $values, array $names, array $map, int $decimals, string $unit): string
    {
        $highestCode = array_search(max($values), $values, true);
        $lowestCode = array_search(min($values), $values, true);

        return sprintf(
            'Höchster Wert: %s mit %s; niedrigster Wert: %s mit %s; %d von %d Regionen mit Daten.',
            $names[$highestCode],
            ChartFormat::withUnit($values[$highestCode], $decimals, $unit),
            $names[$lowestCode],
            ChartFormat::withUnit($values[$lowestCode], $decimals, $unit),
            count($values),
            count($map['regions']),
        );
    }

    private function c(float $value): string
    {
        return $this->shapes->coordinate($value);
    }
}
