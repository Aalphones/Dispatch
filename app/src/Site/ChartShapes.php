<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * SVG-Bausteine für alle Diagramm-Zeichner: Elemente im SVG-Namensraum, Beschriftungen, Hinweise, Rechtecke.
 * Beschriftungen stehen nur als Textknoten oder Attributwert im DOM, nie als Markup.
 */
final class ChartShapes
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    public function __construct(private readonly HTMLDocument $document)
    {
    }

    /** @param array<string, string> $attributes */
    public function svg(string $name, array $attributes = [], ?string $content = null): Element
    {
        $element = $this->document->createElementNS(self::SVG_NAMESPACE, $name);
        foreach ($attributes as $attribute => $value) {
            $element->setAttribute($attribute, $value);
        }
        if ($content !== null) {
            $element->textContent = $content;
        }

        return $element;
    }

    public function coordinate(float $value): string
    {
        return ChartFormat::coordinate($value);
    }

    /** @param list<Element> $children */
    public function group(string $class, array $children = []): Element
    {
        $group = $this->svg('g', $class === '' ? [] : ['class' => $class]);
        foreach ($children as $child) {
            $group->appendChild($child);
        }

        return $group;
    }

    public function label(float $x, float $y, string $content, string $class = '', string $anchor = ''): Element
    {
        $attributes = ['x' => $this->coordinate($x), 'y' => $this->coordinate($y)];
        if ($class !== '') {
            $attributes['class'] = $class;
        }
        if ($anchor !== '') {
            $attributes['text-anchor'] = $anchor;
        }

        return $this->svg('text', $attributes, $content);
    }

    public function withTooltip(Element $shape, string $tooltip): Element
    {
        if ($tooltip !== '') {
            $shape->appendChild($this->svg('title', [], $tooltip));
        }

        return $shape;
    }

    public function box(float $x, float $y, float $width, float $height, string $tooltip, string $class = '', float $radius = 0): Element
    {
        $attributes = [
            'class' => trim('m ' . $class),
            'x' => $this->coordinate($x),
            'y' => $this->coordinate($y),
            'width' => $this->coordinate($width),
            'height' => $this->coordinate($height),
        ];
        if ($radius > 0) {
            $attributes['rx'] = $this->coordinate($radius);
        }

        return $this->withTooltip($this->svg('rect', $attributes), $tooltip);
    }

    /**
     * @param list<Element> $shapes
     * @param list<array{class: string, name: string}> $legend
     * @return array<string, mixed>
     */
    public function plot(float $width, float $height, array $shapes, string $summary, array $legend = [], bool $isScale = false, bool $isWide = false): array
    {
        return ['width' => $width, 'height' => $height, 'shapes' => $shapes, 'summary' => $summary, 'legend' => $legend, 'isScale' => $isScale, 'isWide' => $isWide];
    }

    /**
     * Legende der Reihenfarben r1…r8; eine einzelne Reihe braucht keine.
     *
     * @param list<string> $names
     * @return list<array{class: string, name: string}>
     */
    public function seriesLegend(array $names): array
    {
        if (count($names) < 2) {
            return [];
        }
        $items = [];
        foreach ($names as $index => $name) {
            $items[] = ['class' => 'r' . ($index + 1), 'name' => $name];
        }

        return $items;
    }
}
