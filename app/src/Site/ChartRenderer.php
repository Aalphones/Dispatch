<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Util\Log;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Zeichnet die statischen Diagramm-Typen als SVG auf dem Server.
 *
 * Eingang sind die gelesenen Daten aus {@see ChartData}; alle Elemente entstehen über die DOM-API, Beschriftungen nur als
 * Textknoten oder Attributwerte. Die Zeichnung ist ohne Skript vollständig; Hinweise je Marke stehen als `<title>`.
 * Farben und Maße liegen in diagramme.css (Klassen r1…r8, s0…s5, plus/minus/summe), nie als style-Attribut.
 */
final class ChartRenderer
{
    private const WIDTH = 440.0;
    private const RIGHT_EDGE = 432.0;
    private const AXIS_TOP = 28.0;
    private const AXIS_BOTTOM = 216.0;
    private const COLUMN_HEIGHT = 250.0;
    private const CORNER_RADIUS = 4.0;
    private const STACK_GAP = 1.0;
    private const MAX_PIE_PARTS = 6;
    private const RADAR_RINGS = 5;
    private const WEEKDAY_LABELS = ['Mo', 'Mi', 'Fr'];
    private const MONTH_SHORT = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    private const MONTH_LONG = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    private const DEFAULT_TITLE = 'Diagramm';
    private const TYPE_NAMES = [
        'bar' => 'balken',
        'column' => 'saeulen',
        'line' => 'linie',
        'area' => 'flaeche',
        'stacked-bar' => 'balken-gestapelt',
        'stacked-column' => 'saeulen-gestapelt',
        'pie' => 'kreis',
        'donut' => 'ring',
        'radar' => 'netz',
        'waffle' => 'waffel',
        'waterfall' => 'wasserfall',
        'heatmap' => 'heatmap',
        'calendar' => 'kalender',
        'sankey' => 'sankey',
        'treemap' => 'baumkarte',
        'bubble' => 'blasen',
        'map' => 'karte',
    ];
    /** Druckmuster r1…r8: Breite der Kachel, Drehung, Streifen (Breite, Höhe) oder Vollfläche; die Identität trägt im Druck das Muster. */
    private const PATTERNS = [
        ['size' => 6, 'rotate' => 0, 'bars' => [[6, 6, '#444']]],
        ['size' => 6, 'rotate' => 45, 'bars' => [[1.6, 6, '#000']]],
        ['size' => 6, 'rotate' => 135, 'bars' => [[1.6, 6, '#000']]],
        ['size' => 6, 'rotate' => 45, 'bars' => [[3.2, 6, '#000']]],
        ['size' => 6, 'rotate' => 135, 'bars' => [[3.2, 6, '#000']]],
        ['size' => 6, 'rotate' => 45, 'bars' => [[1.4, 6, '#000'], [6, 1.4, '#000']]],
        ['size' => 4, 'rotate' => 135, 'bars' => [[1.2, 4, '#000']]],
        ['size' => 6, 'rotate' => 0, 'background' => '#bbb', 'bars' => []],
    ];

    private readonly ChartShapes $shapes;
    private readonly ChartComplexPlots $complexPlots;

    public function __construct(private readonly HTMLDocument $document)
    {
        $this->shapes = new ChartShapes($document);
        $this->complexPlots = new ChartComplexPlots($this->shapes);
    }

    public static function supports(string $type): bool
    {
        return isset(self::TYPE_NAMES[$type]);
    }

    /**
     * Unsichtbare Muster-Definitionen für den Druck; einmal je Artikel vor das erste Diagramm setzen.
     */
    public function patterns(): Element
    {
        $svg = $this->svg('svg', ['class' => 'muster', 'width' => '0', 'height' => '0', 'aria-hidden' => 'true', 'focusable' => 'false']);
        $definitions = $this->svg('defs');
        foreach (self::PATTERNS as $index => $pattern) {
            $size = (string) $pattern['size'];
            $attributes = ['id' => 'mu' . ($index + 1), 'width' => $size, 'height' => $size, 'patternUnits' => 'userSpaceOnUse'];
            if ($pattern['rotate'] !== 0) {
                $attributes['patternTransform'] = 'rotate(' . $pattern['rotate'] . ')';
            }
            $tile = $this->svg('pattern', $attributes);
            $tile->appendChild($this->svg('rect', ['width' => $size, 'height' => $size, 'fill' => $pattern['background'] ?? '#fff']));
            foreach ($pattern['bars'] as [$barWidth, $barHeight, $color]) {
                $tile->appendChild($this->svg('rect', ['width' => (string) $barWidth, 'height' => (string) $barHeight, 'fill' => $color]));
            }
            $definitions->appendChild($tile);
        }
        $definitions->appendChild($this->missingDataPattern());
        $svg->appendChild($definitions);

        return $svg;
    }

    /** Schraffur für Regionen ohne Daten (Karte); Farben kommen aus diagramme.css, damit sie dem Thema folgen. */
    private function missingDataPattern(): Element
    {
        $tile = $this->svg('pattern', ['id' => 'mu-leer', 'width' => '6', 'height' => '6', 'patternUnits' => 'userSpaceOnUse', 'patternTransform' => 'rotate(45)']);
        $tile->appendChild($this->svg('rect', ['class' => 'leer-grund', 'width' => '6', 'height' => '6']));
        $tile->appendChild($this->svg('rect', ['class' => 'leer-strich', 'width' => '1.4', 'height' => '6']));

        return $tile;
    }

    /**
     * Baut die ganze Figur; null, wenn die Daten sich nicht zeichnen lassen (der Aufrufer meldet das und lässt die Figur weg).
     *
     * @param array<string, mixed> $data Ergebnis von {@see ChartData::fromFigure()}
     */
    public function render(array $data, string $type, string $unit, int $id, ?Element $table, ?Element $caption): ?Element
    {
        $plot = match ($type) {
            'bar' => $this->bars($data, $unit),
            'column' => $this->columns($data, $unit),
            'line' => $this->lines($data, $unit, false),
            'area' => $this->lines($data, $unit, true),
            'stacked-bar' => $this->stacked($data, $unit, true),
            'stacked-column' => $this->stacked($data, $unit, false),
            'pie' => $this->pie($data, $unit, false),
            'donut' => $this->pie($data, $unit, true),
            'radar' => $this->radar($data, $unit),
            'waffle' => $this->waffle($data),
            'waterfall' => $this->waterfall($data, $unit),
            'heatmap' => $this->heatmap($data, $unit),
            'calendar' => $this->calendar($data, $unit),
            'sankey' => $this->complexPlots->sankey($data, $unit),
            'treemap' => $this->complexPlots->treemap($data, $unit),
            'bubble' => $this->complexPlots->bubbles($data),
            'map' => $this->complexPlots->map($data, $unit),
            default => null,
        };
        if ($plot === null) {
            return null;
        }

        return $this->buildFigure(self::TYPE_NAMES[$type], $plot, $id, $table, $caption);
    }

    /* ---------------------------------------------------------------- Figur */

    /** @param array<string, mixed> $plot */
    private function buildFigure(string $typeName, array $plot, int $id, ?Element $table, ?Element $caption): Element
    {
        $figure = $this->document->createElement('figure');
        $figure->setAttribute('class', 'diagramm');
        $figure->setAttribute('data-typ', $typeName);
        if ($plot['isWide']) {
            $figure->setAttribute('data-breit', '');
        }

        $surface = $this->document->createElement('div');
        $surface->setAttribute('class', 'diagramm-flaeche');
        $svg = $this->svg('svg', [
            'role' => 'img',
            'aria-labelledby' => 'd' . $id . '-titel d' . $id . '-beschreibung',
            'viewBox' => '0 0 ' . ChartFormat::coordinate($plot['width']) . ' ' . ChartFormat::coordinate($plot['height']),
        ]);
        $svg->appendChild($this->svg('title', ['id' => 'd' . $id . '-titel'], $this->titleFrom($caption)));
        $svg->appendChild($this->svg('desc', ['id' => 'd' . $id . '-beschreibung'], $plot['summary']));
        foreach ($plot['shapes'] as $shape) {
            $svg->appendChild($shape);
        }
        $surface->appendChild($svg);
        $figure->appendChild($surface);

        if ($plot['legend'] !== []) {
            $figure->appendChild($this->buildLegend($plot['legend'], $plot['isScale']));
        }
        if ($table !== null) {
            $details = $this->document->createElement('details');
            $details->setAttribute('class', 'diagramm-tabelle');
            $summary = $this->document->createElement('summary');
            $summary->textContent = 'Zahlen als Tabelle';
            $details->appendChild($summary);
            $details->appendChild($table);
            $figure->appendChild($details);
        }
        if ($caption !== null) {
            $newCaption = $this->document->createElement('figcaption');
            while ($caption->firstChild !== null) {
                $newCaption->appendChild($caption->firstChild);
            }
            $figure->appendChild($newCaption);
        }

        return $figure;
    }

    private function titleFrom(?Element $caption): string
    {
        if ($caption === null) {
            return self::DEFAULT_TITLE;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $caption->textContent));
        $text = (string) preg_replace('/^Abbildung\s+\d+\s*:\s*/u', '', $text);
        $sentences = preg_split('/(?<=[.!?])\s/u', $text);
        $first = trim($sentences[0] ?? '');

        return $first === '' ? self::DEFAULT_TITLE : ChartFormat::truncate($first, 120);
    }

    /** @param list<array{class: string, name: string}> $items */
    private function buildLegend(array $items, bool $isScale): Element
    {
        $list = $this->document->createElement('ul');
        $list->setAttribute('class', 'diagramm-legende' . ($isScale ? ' skala' : ''));
        foreach ($items as $item) {
            $entry = $this->document->createElement('li');
            $entry->setAttribute('class', $item['class']);
            $mark = $this->svg('svg', ['class' => 'marke', 'viewBox' => '0 0 13 13', 'aria-hidden' => 'true']);
            $mark->appendChild($this->svg('rect', ['class' => 'm', 'width' => '13', 'height' => '13', 'rx' => $isScale ? '0' : '3']));
            $entry->appendChild($mark);
            $entry->appendChild($this->document->createTextNode($item['name']));
            $list->appendChild($entry);
        }

        return $list;
    }

    /**
     * @param list<Element> $shapes
     * @param list<array{class: string, name: string}> $legend
     * @return array<string, mixed>
     */
    private function plot(float $width, float $height, array $shapes, string $summary, array $legend = [], bool $isScale = false, bool $isWide = false): array
    {
        return $this->shapes->plot($width, $height, $shapes, $summary, $legend, $isScale, $isWide);
    }

    /**
     * @param list<string> $names
     * @return list<array{class: string, name: string}>
     */
    private function seriesLegend(array $names): array
    {
        return $this->shapes->seriesLegend($names);
    }

    /* ------------------------------------------------------- SVG-Bausteine */

    /** @param array<string, string> $attributes */
    private function svg(string $name, array $attributes = [], ?string $content = null): Element
    {
        return $this->shapes->svg($name, $attributes, $content);
    }

    private function coordinate(float $value): string
    {
        return $this->shapes->coordinate($value);
    }

    /** @param list<Element> $children */
    private function group(string $class, array $children = []): Element
    {
        return $this->shapes->group($class, $children);
    }

    private function label(float $x, float $y, string $content, string $class = '', string $anchor = ''): Element
    {
        return $this->shapes->label($x, $y, $content, $class, $anchor);
    }

    private function withTooltip(Element $shape, string $tooltip): Element
    {
        return $this->shapes->withTooltip($shape, $tooltip);
    }

    private function box(float $x, float $y, float $width, float $height, string $tooltip, string $class = '', float $radius = 0): Element
    {
        return $this->shapes->box($x, $y, $width, $height, $tooltip, $class, $radius);
    }

    /**
     * Balken oder Säule mit abgerundetem Datenende (4 Einheiten), Basis eckig.
     *
     * @param string $direction Richtung des Datenendes: `up`, `down`, `right`, `left`
     */
    private function mark(float $x, float $y, float $width, float $height, string $direction, string $tooltip): Element
    {
        $radius = min(self::CORNER_RADIUS, $width / 2, $height / 2);
        $c = fn (float $value): string => $this->coordinate($value);
        $right = $x + $width;
        $bottom = $y + $height;
        $path = match ($direction) {
            'up' => "M{$c($x)} {$c($bottom)}V{$c($y + $radius)}Q{$c($x)} {$c($y)} {$c($x + $radius)} {$c($y)}H{$c($right - $radius)}Q{$c($right)} {$c($y)} {$c($right)} {$c($y + $radius)}V{$c($bottom)}Z",
            'down' => "M{$c($x)} {$c($y)}V{$c($bottom - $radius)}Q{$c($x)} {$c($bottom)} {$c($x + $radius)} {$c($bottom)}H{$c($right - $radius)}Q{$c($right)} {$c($bottom)} {$c($right)} {$c($bottom - $radius)}V{$c($y)}Z",
            'right' => "M{$c($x)} {$c($y)}H{$c($right - $radius)}Q{$c($right)} {$c($y)} {$c($right)} {$c($y + $radius)}V{$c($bottom - $radius)}Q{$c($right)} {$c($bottom)} {$c($right - $radius)} {$c($bottom)}H{$c($x)}Z",
            default => "M{$c($right)} {$c($y)}H{$c($x + $radius)}Q{$c($x)} {$c($y)} {$c($x)} {$c($y + $radius)}V{$c($bottom - $radius)}Q{$c($x)} {$c($bottom)} {$c($x + $radius)} {$c($bottom)}H{$c($right)}Z",
        };

        return $this->withTooltip($this->svg('path', ['class' => 'm', 'd' => $path]), $tooltip);
    }

    /** @return list<float> Werte der Achsenmarken von unten nach oben */
    private function ticks(array $scale): array
    {
        $ticks = [];
        $count = (int) round(($scale['max'] - $scale['min']) / $scale['step']);
        for ($index = 0; $index <= $count; $index++) {
            $ticks[] = round($scale['min'] + $index * $scale['step'], 6);
        }

        return $ticks;
    }

    /** Linker Rand so breit, dass die längste Achsenzahl noch hineinpasst. */
    private function axisMargin(array $scale): float
    {
        $widest = 0.0;
        foreach ($this->ticks($scale) as $tick) {
            $widest = max($widest, ChartFormat::textWidth(ChartFormat::number($tick, $scale['decimals'])));
        }

        return max(36.0, ceil($widest) + 12);
    }

    /**
     * Eine Zahlenachse links mit Haarlinien-Gitter; die Nulllinie ist die Basislinie.
     *
     * @return array{0: Element, 1: callable(float): float} Achsen-Gruppe und Umrechnung Wert → y
     */
    private function verticalAxis(array $scale, float $left, float $right, float $bottom, float $top, string $unit): array
    {
        $toY = fn (float $value): float => $bottom - ($value - $scale['min']) / ($scale['max'] - $scale['min']) * ($bottom - $top);
        $group = $this->group('achse');
        foreach ($this->ticks($scale) as $tick) {
            $y = $toY($tick);
            $group->appendChild($this->svg('line', [
                'class' => abs($tick) < 1e-9 ? 'basis' : 'gitter',
                'x1' => $this->coordinate($left),
                'x2' => $this->coordinate($right),
                'y1' => $this->coordinate($y),
                'y2' => $this->coordinate($y),
            ]));
            $group->appendChild($this->label($left - 6, $y + 4, ChartFormat::number($tick, $scale['decimals']), '', 'end'));
        }
        if ($unit !== '') {
            $group->appendChild($this->label(0, 10, 'in ' . $unit, 'achsentitel'));
        }

        return [$group, $toY];
    }

    /**
     * Beschriftungen unter den Bändern: bei breiten Bändern umbrochen, bei schmalen nur jede k-te.
     *
     * @param list<string> $labels
     * @return list<Element>
     */
    private function categoryLabels(array $labels, float $left, float $band, float $baseline): array
    {
        $count = count($labels);
        $widest = 0.0;
        foreach ($labels as $label) {
            $widest = max($widest, ChartFormat::textWidth($label));
        }
        $isWrapped = $band >= 44;
        $every = $isWrapped ? 1 : (int) max(1, ceil(($widest + 6) / $band));

        $elements = [];
        foreach ($labels as $index => $label) {
            if ($index % $every !== 0) {
                continue;
            }
            $lines = [$label];
            if ($isWrapped) {
                $width = (int) max(3, floor($band / 6.4));
                $wrapped = ChartFormat::wrap($label, $width);
                $lines = array_slice($wrapped, 0, 2);
                if (count($wrapped) > 2) {
                    $lines[1] = ChartFormat::truncate($lines[1] . ' …', $width);
                }
            }
            $group = $this->group('achse');
            foreach ($lines as $line => $text) {
                $group->appendChild($this->label($left + $band * ($index + 0.5), $baseline + 16 + $line * 14, $text, '', 'middle'));
            }
            $elements[] = $group;
        }

        return $elements;
    }

    /** @param list<float> $values */
    private function extremes(array $values): array
    {
        return [min($values), max($values)];
    }

    /* ------------------------------------------------- Säulen und Balken */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function columns(array $data, string $unit): array
    {
        $names = $data['series'];
        $rows = $data['rows'];
        $count = count($names);
        $values = $this->allValues($rows);
        $decimals = ChartFormat::decimalsOf($values);
        $scale = ChartFormat::niceScale(min($values), max($values));
        $left = $this->axisMargin($scale);
        [$axis, $toY] = $this->verticalAxis($scale, $left, self::RIGHT_EDGE, self::AXIS_BOTTOM, self::AXIS_TOP, $unit);
        $zero = $toY(0.0);

        $band = (self::RIGHT_EDGE - $left) / count($rows);
        $barWidth = max(2.0, min(24.0, ($band * 0.72 - ($count - 1) * 2) / $count));
        $groupWidth = $count * $barWidth + ($count - 1) * 2;
        // Mehrere Reihen nebeneinander: nur der Höchstwert je Reihe trägt eine Zahl, sonst überlagern sich die Beschriftungen.
        $labelAll = count($rows) <= 8 && $count === 1;

        $shapes = [$axis];
        foreach ($rows as $index => $row) {
            $groupX = $left + $band * $index + ($band - $groupWidth) / 2;
            foreach ($row['values'] as $seriesIndex => $value) {
                $x = $groupX + $seriesIndex * ($barWidth + 2);
                $top = $toY(max($value, 0.0));
                $height = abs($toY($value) - $zero);
                $tooltip = ($count > 1 ? $names[$seriesIndex] . ', ' : '') . $row['label'] . ': ' . ChartFormat::withUnit($value, $decimals, $unit);
                $mark = $height < 0.5 ? $this->box($x, $zero - 0.5, $barWidth, 1, $tooltip) : $this->mark($x, $value >= 0 ? $top : $zero, $barWidth, $height, $value >= 0 ? 'up' : 'down', $tooltip);
                $children = [$mark];

                $seriesValues = array_column(array_column($rows, 'values'), $seriesIndex);
                if ($labelAll || $value === max($seriesValues)) {
                    $text = ChartFormat::withUnit($value, $decimals, $unit);
                    $centerX = min($x + $barWidth / 2, self::WIDTH - 4 - ChartFormat::textWidth($text) / 2);
                    $children[] = $this->label($centerX, $value >= 0 ? $toY($value) - 5 : $toY($value) + 15, $text, 'wert', 'middle');
                }
                $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
            }
        }
        foreach ($this->categoryLabels(array_column($rows, 'label'), $left, $band, self::AXIS_BOTTOM) as $label) {
            $shapes[] = $label;
        }

        return $this->plot(self::WIDTH, self::COLUMN_HEIGHT, $shapes, $this->summarize($names, $rows, $decimals, $unit), $this->seriesLegend($names));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function bars(array $data, string $unit): array
    {
        $names = $data['series'];
        $rows = $data['rows'];
        $count = count($names);
        $values = $this->allValues($rows);
        $decimals = ChartFormat::decimalsOf($values);
        $scale = ChartFormat::niceScale(min($values), max($values));

        $barHeight = $count === 1 ? 20.0 : 14.0;
        $rowHeight = $count * ($barHeight + 2) + 8;
        $top = 22.0;
        $left = 124.0;
        $widestLabel = 0.0;
        foreach ($values as $value) {
            $widestLabel = max($widestLabel, ChartFormat::textWidth(ChartFormat::withUnit($value, $decimals, $unit)));
        }
        $right = self::RIGHT_EDGE - $widestLabel - 12;
        $height = $top + count($rows) * $rowHeight + 24;
        $toX = fn (float $value): float => $left + ($value - $scale['min']) / ($scale['max'] - $scale['min']) * ($right - $left);

        $axis = $this->group('achse');
        foreach ($this->ticks($scale) as $tick) {
            $x = $toX($tick);
            $axis->appendChild($this->svg('line', [
                'class' => abs($tick) < 1e-9 ? 'basis' : 'gitter',
                'x1' => $this->coordinate($x),
                'x2' => $this->coordinate($x),
                'y1' => $this->coordinate($top - 4),
                'y2' => $this->coordinate($height - 22),
            ]));
            $axis->appendChild($this->label($x, $height - 6, ChartFormat::number($tick, $scale['decimals']), '', 'middle'));
        }
        if ($unit !== '') {
            $axis->appendChild($this->label(0, 10, 'in ' . $unit, 'achsentitel'));
        }

        $zeroX = $toX(0.0);
        $shapes = [$axis];
        foreach ($rows as $index => $row) {
            $rowTop = $top + $index * $rowHeight;
            $lines = array_slice(ChartFormat::wrap($row['label'], 19), 0, 3);
            $labels = $this->group('achse');
            foreach ($lines as $line => $text) {
                $labels->appendChild($this->label($left - 8, $rowTop + $rowHeight / 2 + 4 - (count($lines) - 1) * 7 + $line * 14, $text, '', 'end'));
            }
            $shapes[] = $labels;

            foreach ($row['values'] as $seriesIndex => $value) {
                $barTop = $rowTop + 4 + $seriesIndex * ($barHeight + 2);
                $barEnd = $toX($value);
                $tooltip = ($count > 1 ? $names[$seriesIndex] . ', ' : '') . $row['label'] . ': ' . ChartFormat::withUnit($value, $decimals, $unit);
                $children = [];
                if (abs($barEnd - $zeroX) >= 0.5) {
                    $children[] = $this->mark(min($zeroX, $barEnd), $barTop, abs($barEnd - $zeroX), $barHeight, $value >= 0 ? 'right' : 'left', $tooltip);
                }
                $labelX = $value >= 0 ? $barEnd + 6 : $zeroX + 6;
                $children[] = $this->label($labelX, $barTop + $barHeight / 2 + 4, ChartFormat::withUnit($value, $decimals, $unit), 'wert');
                $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
            }
        }

        return $this->plot(self::WIDTH, $height, $shapes, $this->summarize($names, $rows, $decimals, $unit), $this->seriesLegend($names));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function stacked(array $data, string $unit, bool $isHorizontal): ?array
    {
        $names = $data['series'];
        $rows = $data['rows'];
        $values = $this->allValues($rows);
        if (min($values) < 0) {
            Log::error('Gestapeltes Diagramm mit negativen Werten wird nicht gezeichnet');

            return null;
        }
        $decimals = ChartFormat::decimalsOf($values);
        $totals = array_map(fn (array $row): float => array_sum($row['values']), $rows);
        $scale = ChartFormat::niceScale(0.0, max($totals));
        $lastIndex = count($names) - 1;
        $shapes = [];

        if ($isHorizontal) {
            $left = min(150.0, max(40.0, ceil($this->widest(array_column($rows, 'label'))) + 12));
            $right = self::RIGHT_EDGE - ChartFormat::textWidth(ChartFormat::number(max($totals), $decimals)) - 10;
            $rowHeight = 32.0;
            $top = 22.0;
            $height = $top + count($rows) * $rowHeight + 24;
            $toX = fn (float $value): float => $left + $value / $scale['max'] * ($right - $left);

            $axis = $this->group('achse');
            foreach ($this->ticks($scale) as $tick) {
                $axis->appendChild($this->svg('line', ['class' => $tick == 0 ? 'basis' : 'gitter', 'x1' => $this->coordinate($toX($tick)), 'x2' => $this->coordinate($toX($tick)), 'y1' => $this->coordinate($top - 4), 'y2' => $this->coordinate($height - 22)]));
                $axis->appendChild($this->label($toX($tick), $height - 6, ChartFormat::number($tick, $scale['decimals']), '', 'middle'));
            }
            if ($unit !== '') {
                $axis->appendChild($this->label(0, 10, 'in ' . $unit, 'achsentitel'));
            }
            $shapes[] = $axis;

            foreach ($rows as $index => $row) {
                $y = $top + $index * $rowHeight;
                $shapes[] = $this->group('achse', [$this->label($left - 8, $y + 19, ChartFormat::truncate($row['label'], (int) floor(($left - 8) / 6.6)), '', 'end')]);
                $accumulated = 0.0;
                foreach ($row['values'] as $seriesIndex => $value) {
                    $start = $toX($accumulated) + ($seriesIndex > 0 ? self::STACK_GAP : 0);
                    $end = $toX($accumulated + $value) - ($seriesIndex < $lastIndex ? self::STACK_GAP : 0);
                    $tooltip = $names[$seriesIndex] . ', ' . $row['label'] . ': ' . ChartFormat::withUnit($value, $decimals, $unit);
                    $children = [];
                    if ($end - $start > 0.5) {
                        $children[] = $seriesIndex === $lastIndex ? $this->mark($start, $y + 4, $end - $start, 22, 'right', $tooltip) : $this->box($start, $y + 4, $end - $start, 22, $tooltip);
                        $text = ChartFormat::number($value, $decimals);
                        if (ChartFormat::textWidth($text) + 10 < $end - $start) {
                            $children[] = $this->label(($start + $end) / 2, $y + 19, $text, 'in', 'middle');
                        }
                    }
                    $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
                    $accumulated += $value;
                }
                $shapes[] = $this->label($toX($accumulated) + 6, $y + 19, ChartFormat::number($accumulated, $decimals), 'wert');
            }
        } else {
            $left = $this->axisMargin($scale);
            $height = self::COLUMN_HEIGHT;
            [$axis, $toY] = $this->verticalAxis($scale, $left, self::RIGHT_EDGE, self::AXIS_BOTTOM, self::AXIS_TOP, $unit);
            $shapes[] = $axis;
            $band = (self::RIGHT_EDGE - $left) / count($rows);
            $barWidth = max(2.0, min(40.0, $band * 0.7));
            $unitHeight = self::AXIS_BOTTOM - self::AXIS_TOP;

            foreach ($rows as $index => $row) {
                $x = $left + $band * $index + ($band - $barWidth) / 2;
                $accumulated = 0.0;
                foreach ($row['values'] as $seriesIndex => $value) {
                    $segment = $value / $scale['max'] * $unitHeight;
                    $segmentTop = $toY($accumulated + $value) + ($seriesIndex < $lastIndex ? self::STACK_GAP : 0);
                    $segmentHeight = $segment - ($seriesIndex < $lastIndex ? self::STACK_GAP : 0);
                    $tooltip = $names[$seriesIndex] . ', ' . $row['label'] . ': ' . ChartFormat::withUnit($value, $decimals, $unit);
                    $children = [];
                    if ($segmentHeight > 0.5) {
                        $children[] = $seriesIndex === $lastIndex ? $this->mark($x, $segmentTop, $barWidth, $segmentHeight, 'up', $tooltip) : $this->box($x, $segmentTop, $barWidth, $segmentHeight, $tooltip);
                        if ($segment > 18 && ChartFormat::textWidth(ChartFormat::number($value, $decimals)) + 4 < $barWidth) {
                            $children[] = $this->label($x + $barWidth / 2, $toY($accumulated + $value) + $segment / 2 + 4, ChartFormat::number($value, $decimals), 'in', 'middle');
                        }
                    }
                    $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
                    $accumulated += $value;
                }
                $shapes[] = $this->label($x + $barWidth / 2, $toY($accumulated) - 5, ChartFormat::number($accumulated, $decimals), 'wert', 'middle');
            }
            foreach ($this->categoryLabels(array_column($rows, 'label'), $left, $band, self::AXIS_BOTTOM) as $label) {
                $shapes[] = $label;
            }
        }

        return $this->plot(self::WIDTH, $height, $shapes, $this->summarize($names, $rows, $decimals, $unit), $this->seriesLegend($names));
    }

    /* --------------------------------------------------- Linie und Fläche */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function lines(array $data, string $unit, bool $isArea): array
    {
        $names = $data['series'];
        $rows = $data['rows'];
        $values = $this->allValues($rows);
        $decimals = ChartFormat::decimalsOf($values);
        $scale = ChartFormat::niceScale(min($values), max($values));
        $left = $this->axisMargin($scale);
        $right = max(260.0, self::RIGHT_EDGE - min(110.0, $this->widest($names)) - 14);
        [$axis, $toY] = $this->verticalAxis($scale, $left, $right + 4, self::AXIS_BOTTOM, self::AXIS_TOP, $unit);
        $shapes = [$axis];

        $count = count($rows);
        $firstX = $left + 10;
        $stepX = $count > 1 ? ($right - $left - 20) / ($count - 1) : 0.0;
        $toX = fn (int $index): float => $count > 1 ? $firstX + $index * $stepX : ($left + $right) / 2;

        $every = $count > 1 ? (int) max(1, ceil(($this->widest(array_column($rows, 'label')) + 8) / $stepX)) : 1;
        foreach ($rows as $index => $row) {
            if ($index % $every === 0) {
                $shapes[] = $this->group('achse', [$this->label($toX($index), self::AXIS_BOTTOM + 16, $row['label'], '', 'middle')]);
            }
        }

        $endLabels = [];
        foreach ($names as $seriesIndex => $name) {
            $points = [];
            $path = '';
            foreach ($rows as $index => $row) {
                $x = $toX($index);
                $y = $toY($row['values'][$seriesIndex]);
                $points[] = [$x, $y];
                $path .= ($index === 0 ? 'M' : 'L') . $this->coordinate($x) . ' ' . $this->coordinate($y);
            }
            $children = [];
            if ($isArea) {
                $baseline = $this->coordinate($toY(0.0));
                $children[] = $this->svg('path', ['class' => 'flaeche-fuell', 'd' => $path . 'L' . $this->coordinate(end($points)[0]) . ' ' . $baseline . 'L' . $this->coordinate($points[0][0]) . ' ' . $baseline . 'Z']);
            }
            $children[] = $this->withTooltip($this->svg('path', ['class' => 'l', 'd' => $path]), $name);
            $lastIndex = $count - 1;
            [$lastX, $lastY] = end($points);
            $children[] = $this->withTooltip(
                $this->svg('circle', ['class' => 'punkt', 'cx' => $this->coordinate($lastX), 'cy' => $this->coordinate($lastY), 'r' => '4']),
                $name . ', ' . $rows[$lastIndex]['label'] . ': ' . ChartFormat::withUnit($rows[$lastIndex]['values'][$seriesIndex], $decimals, $unit),
            );
            $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
            $endLabels[] = ['series' => $seriesIndex, 'x' => $lastX, 'y' => $lastY + 4, 'name' => $name];
        }

        // Direktbeschriftung am Linienende; enge Enden werden auseinandergezogen.
        usort($endLabels, fn (array $first, array $second): int => $first['y'] <=> $second['y']);
        foreach ($endLabels as $position => $end) {
            if ($position > 0 && $end['y'] - $endLabels[$position - 1]['y'] < 14) {
                $endLabels[$position]['y'] = $endLabels[$position - 1]['y'] + 14;
            }
        }
        foreach ($endLabels as $end) {
            $shapes[] = $this->group('r' . ($end['series'] + 1), [$this->label($end['x'] + 9, $end['y'], ChartFormat::truncate($end['name'], 16), 'wert halo')]);
        }

        return $this->plot(self::WIDTH, self::COLUMN_HEIGHT, $shapes, $this->summarize($names, $rows, $decimals, $unit), $this->seriesLegend($names));
    }

    /* ---------------------------------------------- Kreis, Ring und Waffel */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function pie(array $data, string $unit, bool $isRing): ?array
    {
        $parts = array_slice($data['parts'], 0, self::MAX_PIE_PARTS);
        $sum = array_sum(array_column($parts, 'value'));
        if ($sum <= 0) {
            Log::error('Kreisdiagramm ohne positive Summe wird nicht gezeichnet');

            return null;
        }
        $decimals = ChartFormat::decimalsOf(array_column($parts, 'value'));
        $percent = ChartFormat::largestRemainder(array_column($parts, 'value'));
        $height = 280.0;
        $centerX = 220.0;
        $centerY = 140.0;
        $outer = 92.0;
        $inner = $isRing ? 56.0 : 0.0;
        $point = fn (float $radius, float $angle): array => [$centerX + $radius * cos($angle), $centerY + $radius * sin($angle)];
        $c = fn (float $value): string => $this->coordinate($value);

        $shapes = [];
        $labels = [];
        $angle = -M_PI / 2;
        foreach ($parts as $index => $part) {
            if ($part['value'] <= 0) {
                continue;
            }
            $sweep = $part['value'] / $sum * 2 * M_PI;
            $tooltip = $part['label'] . ': ' . ChartFormat::withUnit($part['value'], $decimals, $unit) . ' (' . $percent[$index] . ' %)';
            if ($sweep >= 2 * M_PI - 1e-6) {
                $path = "M{$c($centerX - $outer)} {$c($centerY)}A{$outer} {$outer} 0 1 1 {$c($centerX + $outer)} {$c($centerY)}A{$outer} {$outer} 0 1 1 {$c($centerX - $outer)} {$c($centerY)}Z";
                if ($isRing) {
                    $path .= "M{$c($centerX - $inner)} {$c($centerY)}A{$inner} {$inner} 0 1 0 {$c($centerX + $inner)} {$c($centerY)}A{$inner} {$inner} 0 1 0 {$c($centerX - $inner)} {$c($centerY)}Z";
                }
            } else {
                $end = $angle + $sweep;
                $large = $sweep > M_PI ? 1 : 0;
                [$startX, $startY] = $point($outer, $angle);
                [$endX, $endY] = $point($outer, $end);
                $path = "M{$c($startX)} {$c($startY)}A{$outer} {$outer} 0 {$large} 1 {$c($endX)} {$c($endY)}";
                if ($isRing) {
                    [$innerEndX, $innerEndY] = $point($inner, $end);
                    [$innerStartX, $innerStartY] = $point($inner, $angle);
                    $path .= "L{$c($innerEndX)} {$c($innerEndY)}A{$inner} {$inner} 0 {$large} 0 {$c($innerStartX)} {$c($innerStartY)}Z";
                } else {
                    $path .= "L{$c($centerX)} {$c($centerY)}Z";
                }
            }
            $slice = $this->withTooltip($this->svg('path', ['class' => 'm ring', 'd' => $path, 'fill-rule' => 'evenodd']), $tooltip);
            $shapes[] = $this->group('r' . ($index + 1), [$slice]);
            $labels[] = ['mid' => $angle + $sweep / 2, 'text' => ChartFormat::truncate($part['label'], 16) . ' ' . $percent[$index] . ' %'];
            $angle += $sweep;
        }

        foreach ([true, false] as $isRight) {
            $side = array_values(array_filter($labels, fn (array $label): bool => (cos($label['mid']) >= 0) === $isRight));
            usort($side, fn (array $first, array $second): int => sin($first['mid']) <=> sin($second['mid']));
            $previousY = null;
            foreach ($side as $entry) {
                $y = $centerY + ($outer + 12) * sin($entry['mid']);
                if ($previousY !== null && $y - $previousY < 15) {
                    $y = $previousY + 15;
                }
                $previousY = $y;
                $x = $centerX + ($outer + 12) * cos($entry['mid']) + ($isRight ? 4 : -4);
                $shapes[] = $this->label($x, $y + 4, $entry['text'], 'wert', $isRight ? 'start' : 'end');
            }
        }
        if ($isRing) {
            $shapes[] = $this->label($centerX, $centerY + 6, ChartFormat::withUnit($sum, $decimals, $unit), 'wert gross', 'middle');
            $shapes[] = $this->label($centerX, $centerY + 24, 'gesamt', 'skala-text', 'middle');
        }

        $summary = $this->summarizeParts($parts, $percent);
        $legend = [];
        foreach ($parts as $index => $part) {
            if (mb_strlen($part['label']) > 16) {
                $legend = $this->seriesLegend(array_column($parts, 'label'));
                break;
            }
        }

        return $this->plot(self::WIDTH, $height, $shapes, $summary, $legend);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function waffle(array $data): ?array
    {
        $parts = array_slice($data['parts'], 0, self::MAX_PIE_PARTS);
        if (array_sum(array_column($parts, 'value')) <= 0) {
            Log::error('Waffeldiagramm ohne positive Summe wird nicht gezeichnet');

            return null;
        }
        $percent = ChartFormat::largestRemainder(array_column($parts, 'value'));
        $cell = 15.0;
        $gap = 3.2;
        $left = 20.0;
        $top = 14.0;

        $sequence = [];
        foreach ($percent as $index => $share) {
            for ($count = 0; $count < $share; $count++) {
                $sequence[] = $index;
            }
        }
        // Reihe 1 liegt unten, es wird von links nach rechts und nach oben gefüllt.
        $shapes = [];
        foreach ($sequence as $position => $index) {
            $x = $left + ($position % 10) * ($cell + $gap);
            $y = $top + (9 - intdiv($position, 10)) * ($cell + $gap);
            $square = $this->box($x, $y, $cell, $cell, $parts[$index]['label'] . ': ' . $percent[$index] . ' %', '', 3);
            $shapes[] = $this->group('r' . ($index + 1), [$square]);
        }
        foreach ($parts as $index => $part) {
            $y = 40 + $index * 44;
            $shapes[] = $this->group('r' . ($index + 1), [$this->box(230, $y - 14, 14, 14, '', '', 3)]);
            $shapes[] = $this->label(254, $y, $percent[$index] . ' %', 'wert gross');
            $shapes[] = $this->label(254, $y + 18, ChartFormat::truncate($part['label'], 26));
        }
        $noteY = max(196.0, 40 + (count($parts) - 1) * 44 + 18 + 24.0);
        $height = $noteY + 18;
        $shapes[] = $this->label(230, $noteY, '1 Kästchen = 1 % der Gesamtmenge', 'skala-text');

        return $this->plot(self::WIDTH, $height, $shapes, $this->summarizeParts($parts, $percent));
    }

    /* ----------------------------------------------------------- Netz */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function radar(array $data, string $unit): ?array
    {
        $names = $data['series'];
        $rows = $data['rows'];
        $axisCount = count($rows);
        $values = $this->allValues($rows);
        if ($axisCount < 3 || min($values) < 0) {
            Log::error('Netzdiagramm braucht mindestens drei Achsen und keine negativen Werte');

            return null;
        }
        $decimals = ChartFormat::decimalsOf($values);
        $scale = ChartFormat::niceScale(0.0, max($values));
        $rings = (int) round($scale['max'] / $scale['step']);
        $centerX = 220.0;
        $centerY = 160.0;
        $radius = 104.0;
        $point = fn (float $length, int $index): array => [
            $centerX + $length * cos(-M_PI / 2 + $index * 2 * M_PI / $axisCount),
            $centerY + $length * sin(-M_PI / 2 + $index * 2 * M_PI / $axisCount),
        ];
        $polygon = function (float $length, callable $lengthAt) use ($axisCount, $point): string {
            $points = [];
            for ($index = 0; $index < $axisCount; $index++) {
                [$x, $y] = $point($lengthAt($index, $length), $index);
                $points[] = $this->coordinate($x) . ',' . $this->coordinate($y);
            }

            return implode(' ', $points);
        };

        $grid = $this->group('achse');
        for ($ring = 1; $ring <= $rings; $ring++) {
            $length = $radius * $ring / $rings;
            $grid->appendChild($this->svg('polygon', ['class' => 'gitter', 'points' => $polygon($length, fn (int $index, float $given): float => $given)]));
            $grid->appendChild($this->label($centerX + 5, $centerY - $length + 12, ChartFormat::number($scale['step'] * $ring, $scale['decimals']), 'skala-text'));
        }
        foreach ($rows as $index => $row) {
            [$edgeX, $edgeY] = $point($radius, $index);
            [$labelX, $labelY] = $point($radius + 14, $index);
            $anchor = abs($labelX - $centerX) < 8 ? 'middle' : ($labelX > $centerX ? 'start' : 'end');
            $grid->appendChild($this->svg('line', ['class' => 'gitter', 'x1' => $this->coordinate($centerX), 'y1' => $this->coordinate($centerY), 'x2' => $this->coordinate($edgeX), 'y2' => $this->coordinate($edgeY)]));
            $grid->appendChild($this->label($labelX, $labelY + 4, ChartFormat::truncate($row['label'], 14), '', $anchor));
        }

        $shapes = [$grid];
        foreach ($names as $seriesIndex => $name) {
            $length = fn (int $index, float $given): float => $radius * $rows[$index]['values'][$seriesIndex] / $scale['max'];
            $children = [$this->withTooltip($this->svg('polygon', ['class' => 'flaeche-fuell l', 'points' => $polygon(0.0, $length)]), $name)];
            foreach ($rows as $index => $row) {
                [$x, $y] = $point($length($index, 0.0), $index);
                $children[] = $this->withTooltip(
                    $this->svg('circle', ['class' => 'punkt', 'cx' => $this->coordinate($x), 'cy' => $this->coordinate($y), 'r' => '4']),
                    $name . ', ' . $row['label'] . ': ' . ChartFormat::withUnit($row['values'][$seriesIndex], $decimals, $unit),
                );
            }
            $shapes[] = $this->group('r' . ($seriesIndex + 1), $children);
        }

        return $this->plot(self::WIDTH, 310.0, $shapes, $this->summarize($names, $rows, $decimals, $unit), $this->seriesLegend($names));
    }

    /* ------------------------------------------------------ Wasserfall */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function waterfall(array $data, string $unit): array
    {
        $decimals = ChartFormat::decimalsOf(array_column($data['steps'], 'value'));
        $running = 0.0;
        $bars = [];
        foreach ($data['steps'] as $step) {
            if ($step['sum']) {
                $bars[] = ['from' => 0.0, 'to' => $step['value'], 'isSum' => true, 'label' => $step['label'], 'value' => $step['value']];
                $running = $step['value'];
                continue;
            }
            $bars[] = ['from' => $running, 'to' => $running + $step['value'], 'isSum' => false, 'label' => $step['label'], 'value' => $step['value']];
            $running += $step['value'];
        }
        $levels = array_merge(array_column($bars, 'from'), array_column($bars, 'to'));
        $scale = ChartFormat::niceScale(min($levels), max($levels) * 1.04);
        $left = $this->axisMargin($scale);
        [$axis, $toY] = $this->verticalAxis($scale, $left, self::RIGHT_EDGE, self::AXIS_BOTTOM, 30.0, $unit);
        $band = (self::RIGHT_EDGE - $left) / count($bars);
        $barWidth = max(2.0, min(36.0, $band * 0.7));

        $shapes = [$axis];
        foreach ($bars as $index => $bar) {
            $x = $left + $band * $index + ($band - $barWidth) / 2;
            $high = max($bar['from'], $bar['to']);
            $low = min($bar['from'], $bar['to']);
            $class = $bar['isSum'] ? 'summe' : ($bar['value'] >= 0 ? 'plus' : 'minus');
            $text = $bar['isSum']
                ? ChartFormat::number($bar['value'], $decimals)
                : ($bar['value'] >= 0 ? '+' : ChartFormat::MINUS) . ChartFormat::number(abs($bar['value']), $decimals);
            $kind = $bar['isSum'] ? 'Summe ' : ($bar['value'] >= 0 ? 'Anstieg ' : 'Rückgang ');
            $tooltip = $bar['label'] . ': ' . $kind . $text . ($unit === '' ? '' : ChartFormat::THIN_SPACE . $unit);
            $height = max(1.0, $toY($low) - $toY($high));
            $direction = ($bar['isSum'] && $bar['value'] < 0) || (!$bar['isSum'] && $bar['value'] < 0) ? 'down' : 'up';
            $shapes[] = $this->group($class, [$this->mark($x, $toY($high), $barWidth, $height, $direction, $tooltip)]);
            $shapes[] = $this->label($x + $barWidth / 2, $toY($high) - 5, $text, 'wert', 'middle');
            if ($index < count($bars) - 1) {
                $shapes[] = $this->svg('line', ['class' => 'verbinder', 'x1' => $this->coordinate($x + $barWidth), 'x2' => $this->coordinate($x + $band), 'y1' => $this->coordinate($toY($bar['to'])), 'y2' => $this->coordinate($toY($bar['to']))]);
            }
        }
        foreach ($this->categoryLabels(array_column($bars, 'label'), $left, $band, self::AXIS_BOTTOM) as $label) {
            $shapes[] = $label;
        }

        $last = end($bars);
        $summary = sprintf('Verlauf von %s bis %s; Endwert %s.', $bars[0]['label'], $last['label'], ChartFormat::withUnit($last['to'], $decimals, $unit));

        return $this->plot(self::WIDTH, self::COLUMN_HEIGHT, $shapes, $summary);
    }

    /* ----------------------------------------- Heatmap und Kalender */

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function heatmap(array $data, string $unit): array
    {
        $columns = $data['columns'];
        $rows = $data['rows'];
        $values = $this->allValues($rows);
        $decimals = ChartFormat::decimalsOf($values);
        [$lowest, $highest] = $this->extremes($values);
        $step = $highest > $lowest ? ($highest - $lowest) / 5 : 1.0;
        $levelOf = fn (float $value): int => (int) min(5, 1 + floor(($value - $lowest) / $step));

        $left = min(130.0, max(40.0, ceil($this->widest(array_column($rows, 'label'))) + 12));
        $top = 22.0;
        $cellWidth = (self::RIGHT_EDGE - $left) / count($columns);
        $cellHeight = 28.0;
        $height = $top + count($rows) * $cellHeight + 6;

        $shapes = [];
        foreach ($this->categoryLabels($columns, $left, $cellWidth, -2.0) as $header) {
            $shapes[] = $header;
        }
        $labels = $this->group('achse');
        foreach ($rows as $rowIndex => $row) {
            $labels->appendChild($this->label($left - 8, $top + $rowIndex * $cellHeight + 18, ChartFormat::truncate($row['label'], (int) floor(($left - 8) / 6.6)), '', 'end'));
        }
        $shapes[] = $labels;

        $showValues = $cellWidth >= 28;
        foreach ($rows as $rowIndex => $row) {
            foreach ($row['values'] as $columnIndex => $value) {
                $x = $left + $cellWidth * $columnIndex;
                $y = $top + $rowIndex * $cellHeight;
                $children = [$this->box($x + 1, $y + 1, $cellWidth - 2, $cellHeight - 2, $row['label'] . ', ' . $columns[$columnIndex] . ': ' . ChartFormat::withUnit($value, $decimals, $unit), '', 3)];
                if ($showValues && ChartFormat::textWidth(ChartFormat::number($value, $decimals)) + 6 < $cellWidth) {
                    $children[] = $this->label($x + $cellWidth / 2, $y + 19, ChartFormat::number($value, $decimals), 'in', 'middle');
                }
                $shapes[] = $this->group('s' . $levelOf($value), $children);
            }
        }

        $legend = [];
        for ($level = 1; $level <= 5; $level++) {
            $legend[] = [
                'class' => 's' . $level,
                'name' => ChartFormat::number($lowest + $step * ($level - 1), $decimals) . '–' . ChartFormat::number($lowest + $step * $level, $decimals),
            ];
        }

        return $this->plot(self::WIDTH, $height, $shapes, $this->summarize($columns, $rows, $decimals, $unit), $legend, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function calendar(array $data, string $unit): array
    {
        $valueByDate = [];
        foreach ($data['days'] as $day) {
            $valueByDate[$day['date']] = $day['value'];
        }
        $year = (int) substr((string) array_key_first($valueByDate), 0, 4);
        $decimals = ChartFormat::decimalsOf(array_values($valueByDate));
        $highest = max($valueByDate);
        $firstDay = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $daysInYear = (int) $firstDay->format('L') === 1 ? 366 : 365;
        $offset = (int) $firstDay->format('N') - 1; // Montag oben
        $cell = 10.0;
        $gap = 1.4;
        $left = 28.0;
        $top = 30.0;
        $width = 648.0;
        $height = $top + 7 * ($cell + $gap) + 12;
        $unitStep = $highest > 0 ? $highest / 5 : 1.0;
        $levelOf = fn (float $value): string => $value <= 0 ? 's0' : 's' . (int) min(5, max(1, ceil($value / $unitStep)));

        $axis = $this->group('achse');
        foreach (self::WEEKDAY_LABELS as $index => $text) {
            $axis->appendChild($this->label($left - 5, $top + $index * 2 * ($cell + $gap) + 9, $text, '', 'end'));
        }
        $cells = [];
        $previousMonth = -1;
        for ($dayIndex = 0; $dayIndex < $daysInYear; $dayIndex++) {
            $date = $firstDay->modify('+' . $dayIndex . ' days');
            $position = $dayIndex + $offset;
            $x = $left + intdiv($position, 7) * ($cell + $gap);
            $y = $top + ($position % 7) * ($cell + $gap);
            $month = (int) $date->format('n') - 1;
            if ($month !== $previousMonth && (int) $date->format('j') <= 7) {
                $previousMonth = $month;
                $axis->appendChild($this->label($x, $top - 8, self::MONTH_SHORT[$month]));
            }
            $value = $valueByDate[$date->format('Y-m-d')] ?? 0.0;
            $tooltip = (int) $date->format('j') . '. ' . self::MONTH_LONG[$month] . ': ' . ($value !== 0.0 ? ChartFormat::withUnit($value, $decimals, $unit) : 'keine Daten');
            $cells[] = $this->group($levelOf($value), [$this->box($x, $y, $cell, $cell, $tooltip, '', 2)]);
        }

        $legend = [['class' => 's0', 'name' => '0']];
        for ($level = 1; $level <= 5; $level++) {
            $legend[] = ['class' => 's' . $level, 'name' => ChartFormat::number(round($unitStep * ($level - 1), $decimals), $decimals) . '–' . ChartFormat::number(round($unitStep * $level, $decimals), $decimals)];
        }
        $peak = array_search($highest, $valueByDate, true);
        $summary = sprintf('Kalender %d: höchster Wert am %s mit %s, Summe %s.', $year, (new \DateTimeImmutable((string) $peak))->format('d.m.'), ChartFormat::withUnit($highest, $decimals, $unit), ChartFormat::withUnit(array_sum($valueByDate), $decimals, $unit));

        return $this->plot($width, $height, array_merge([$axis], $cells), $summary, $legend, true, true);
    }

    /* --------------------------------------------------- Hilfsfunktionen */

    /**
     * @param list<array{values: list<float>}> $rows
     * @return list<float>
     */
    private function allValues(array $rows): array
    {
        return array_merge(...array_column($rows, 'values'));
    }

    /** @param list<string> $texts */
    private function widest(array $texts): float
    {
        $widest = 0.0;
        foreach ($texts as $text) {
            $widest = max($widest, ChartFormat::textWidth($text));
        }

        return $widest;
    }

    /**
     * Kernaussage für die Beschreibung des Diagramms: größter und kleinster Wert mit Ort.
     *
     * @param list<string> $names
     * @param list<array{label: string, values: list<float>}> $rows
     */
    private function summarize(array $names, array $rows, int $decimals, string $unit): string
    {
        $highest = null;
        $lowest = null;
        foreach ($rows as $row) {
            foreach ($row['values'] as $seriesIndex => $value) {
                $place = (count($names) > 1 ? $names[$seriesIndex] . ', ' : '') . $row['label'];
                if ($highest === null || $value > $highest[0]) {
                    $highest = [$value, $place];
                }
                if ($lowest === null || $value < $lowest[0]) {
                    $lowest = [$value, $place];
                }
            }
        }

        return sprintf(
            'Höchster Wert: %s mit %s; niedrigster Wert: %s mit %s.',
            $highest[1],
            ChartFormat::withUnit($highest[0], $decimals, $unit),
            $lowest[1],
            ChartFormat::withUnit($lowest[0], $decimals, $unit),
        );
    }

    /**
     * @param list<array{label: string, value: float}> $parts
     * @param list<int> $percent
     */
    private function summarizeParts(array $parts, array $percent): string
    {
        $largest = array_search(max($percent), $percent, true);
        $smallest = array_search(min($percent), $percent, true);

        return sprintf('Größter Teil: %s mit %d %%; kleinster Teil: %s mit %d %%.', $parts[$largest]['label'], $percent[$largest], $parts[$smallest]['label'], $percent[$smallest]);
    }
}
