<?php

declare(strict_types=1);

namespace Dispatch\Util;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * HTML-Whitelist für eingereichte Artikel und Dokumente (Critical Rule 3).
 *
 * Der HTML5-Parser liest das Markup wie ein Browser; ein anderer Parser (libxml/HTML4) würde einen
 * anderen Baum sehen als der Browser rendert und Mutation-XSS ermöglichen.
 */
final class Sanitizer
{
    private const HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    private const ALLOWED_ELEMENTS = [
        'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'figure', 'figcaption', 'aside', 'section', 'details', 'summary', 'blockquote', 'div', 'span',
        'a', 'strong', 'em', 'b', 'i', 'u', 's', 'sub', 'sup', 'small', 'mark', 'abbr', 'cite', 'q',
        'code', 'pre', 'kbd', 'br', 'hr', 'time',
    ];

    private const DROPPED_ELEMENTS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'option', 'textarea',
        'svg', 'math', 'template', 'noscript',
        'img', 'picture', 'video', 'audio', 'source', 'track', 'canvas',
        'link', 'meta', 'base', 'head', 'title',
    ];

    private const ALLOWED_CLASSES = [
        'lead', 'tldr', 'callout', 'note', 'warning', 'key', 'factbox', 'pullquote', 'stats', 'stat',
        'timeline', 'steps', 'checklist', 'two-col', 'pros', 'cons', 'compare', 'qa', 'definition',
        'details', 'opinion', 'update', 'sidenote', 'dropcap', 'gallery',
        'before-after', 'hotspots', 'rating', 'accordion', 'highlight', 'chart',
    ];

    public const CHART_TYPES = [
        'bar', 'column', 'line', 'area', 'stacked-bar', 'stacked-column', 'pie', 'donut', 'radar', 'waffle',
        'waterfall', 'heatmap', 'calendar', 'sankey', 'treemap', 'bubble', 'map',
    ];
    public const CHART_MAPS = ['de', 'welt'];
    private const UNIT_PATTERN = '/^[^<>"|]{1,12}\z/u';

    private const GALLERY_VARIANTS = ['mosaic', 'carousel', 'strips'];
    private const RATING_MAXIMA = ['5', '10', '100'];

    private const ALLOWED_LAYOUTS = ['inline', 'wide', 'full', 'left', 'right'];
    private const ALLOWED_EMBEDS = ['youtube', 'vimeo'];
    private const ALLOWED_SCOPES = ['row', 'col', 'rowgroup', 'colgroup'];
    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const CITE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,59}(,[A-Za-z0-9][A-Za-z0-9._-]{0,59})*\z/';
    private const MEDIA_PATTERN = '/^m[0-9a-f]{10}\z/';
    private const GALLERY_MEDIA_PATTERN = '/^m[0-9a-f]{10}(,m[0-9a-f]{10}){0,29}\z/';
    private const PAIR_MEDIA_PATTERN = '/^m[0-9a-f]{10},m[0-9a-f]{10}\z/';
    private const LABELS_PATTERN = '/^[^|<>"]{1,40}\|[^|<>"]{1,40}\z/u';
    private const COORDINATE_PATTERN = '/^(100|[0-9]{1,2})(\.[0-9])?\z/';
    private const SCORE_PATTERN = '/^[0-9]{1,3}(\.[0-9])?\z/';
    private const EMBED_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}\z/';
    private const SPAN_PATTERN = '/^[0-9]{1,2}\z/';
    private const SPAN_MAX = 50;
    private const LIST_START_PATTERN = '/^-?[0-9]{1,9}\z/';
    private const DATETIME_PATTERN = '/^\d{4}(-\d{2}(-\d{2}(T[\d:]{5,8}(Z|[+-]\d{2}:\d{2})?)?)?)?\z/';

    private const NAME_DISPLAY_LENGTH = 60;

    /** @var array<string, int> Hinweistext → Anzahl, in Reihenfolge des ersten Auftretens */
    private array $notes = [];

    public function sanitize(string $html): SanitizeResult
    {
        if ($html === '') {
            return new SanitizeResult('', []);
        }

        $this->notes = [];
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8',
        );

        $body = $document->body;
        if ($body === null || $body->localName !== 'body') {
            // Ein <frameset> am Anfang ersetzt <body> im HTML5-Parser; dann bleibt nichts Erlaubtes übrig.
            $this->note('Element <frameset> samt Inhalt entfernt');

            return new SanitizeResult('', $this->collectNotes());
        }

        $this->sanitizeChildren($document, $body);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHtml($child);
        }

        return new SanitizeResult($output, $this->collectNotes());
    }

    private function sanitizeChildren(HTMLDocument $document, Node $parent): void
    {
        foreach (iterator_to_array($parent->childNodes, false) as $child) {
            $this->sanitizeNode($document, $parent, $child);
        }
    }

    private function sanitizeNode(HTMLDocument $document, Node $parent, Node $node): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return;
        }

        // Kommentare, CDATA und Processing Instructions fliegen ohne Hinweis.
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            $parent->removeChild($node);

            return;
        }

        /** @var Element $node */
        $element = $node;
        $name = strtolower($element->localName);

        // Elemente fremder Namensräume (SVG, MathML) haben keinen HTML-Namen, auf den man sich verlassen könnte.
        if ($element->namespaceURI !== self::HTML_NAMESPACE) {
            $this->dropElement($parent, $element, $name);

            return;
        }

        if ($name === 'h1') {
            $element = $this->renameToHeading2($document, $parent, $element);
            $name = 'h2';
        }

        if (in_array($name, self::DROPPED_ELEMENTS, true)) {
            $this->dropElement($parent, $element, $name);

            return;
        }

        if (!in_array($name, self::ALLOWED_ELEMENTS, true)) {
            $this->note('Element <' . $this->display($name) . '> entfernt, Inhalt behalten');
            $this->sanitizeChildren($document, $element);
            $this->unwrap($parent, $element);

            return;
        }

        $this->sanitizeAttributes($element, $name);
        $this->sanitizeChildren($document, $element);
    }

    private function dropElement(Node $parent, Element $element, string $name): void
    {
        $this->note('Element <' . $this->display($name) . '> samt Inhalt entfernt');
        $parent->removeChild($element);
    }

    private function renameToHeading2(HTMLDocument $document, Node $parent, Element $element): Element
    {
        $this->note('Element <h1> zu <h2> umgewandelt');

        $replacement = $document->createElement('h2');
        foreach ($element->attributes as $attribute) {
            $replacement->setAttribute($attribute->name, $attribute->value);
        }
        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }
        $parent->replaceChild($replacement, $element);

        return $replacement;
    }

    private function unwrap(Node $parent, Element $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function sanitizeAttributes(Element $element, string $name): void
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        $classes = $this->filterClasses($element, $attributes['class'] ?? null);
        unset($attributes['class']);

        foreach ($attributes as $attributeName => $value) {
            $keptValue = $this->keptAttributeValue($name, $attributeName, $value, $classes);
            if ($keptValue === null) {
                $element->removeAttribute($attributeName);
                $this->noteRemovedAttribute($name, $attributeName);

                continue;
            }
            if ($keptValue !== $value) {
                $element->setAttribute($attributeName, $keptValue);
            }
        }
    }

    /**
     * @return list<string> die behaltenen Klassen
     */
    private function filterClasses(Element $element, ?string $classAttribute): array
    {
        if ($classAttribute === null) {
            return [];
        }

        $kept = [];
        foreach (preg_split('/\s+/', trim($classAttribute), -1, PREG_SPLIT_NO_EMPTY) as $class) {
            if (in_array($class, self::ALLOWED_CLASSES, true)) {
                if (!in_array($class, $kept, true)) {
                    $kept[] = $class;
                }
            } else {
                $this->note('Klasse ' . $this->display($class) . ' entfernt');
            }
        }

        if ($kept === []) {
            $element->removeAttribute('class');
        } else {
            $element->setAttribute('class', implode(' ', $kept));
        }

        return $kept;
    }

    /**
     * @param list<string> $classes
     *
     * @return string|null der zu speichernde Wert, null = Attribut entfernen
     */
    private function keptAttributeValue(string $element, string $attribute, string $value, array $classes): ?string
    {
        return match ($element . ' ' . $attribute) {
            'a href' => $this->safeHref($value),
            'a title', 'abbr title' => $value,
            'a data-cite' => $this->matching(self::CITE_PATTERN, $value),
            'figure data-media' => $this->matching(
                in_array('before-after', $classes, true) ? self::PAIR_MEDIA_PATTERN : self::MEDIA_PATTERN,
                $value,
            ),
            'figure data-labels' => in_array('before-after', $classes, true)
                ? $this->matching(self::LABELS_PATTERN, $value)
                : null,
            'div data-variant' => in_array('gallery', $classes, true)
                ? $this->oneOf(self::GALLERY_VARIANTS, $value)
                : null,
            'figure data-type' => in_array('chart', $classes, true)
                ? $this->oneOf(self::CHART_TYPES, $value)
                : null,
            'figure data-unit' => in_array('chart', $classes, true)
                ? $this->matching(self::UNIT_PATTERN, $value)
                : null,
            'figure data-map' => in_array('chart', $classes, true)
                ? $this->oneOf(self::CHART_MAPS, $value)
                : null,
            'figure data-interactive' => in_array('chart', $classes, true)
                ? $this->oneOf(['true'], $value)
                : null,
            'tr data-sum' => $this->oneOf(['true'], $value),
            'li data-x', 'li data-y' => $this->matching(self::COORDINATE_PATTERN, $value),
            'ul data-max' => in_array('rating', $classes, true)
                ? $this->oneOf(self::RATING_MAXIMA, $value)
                : null,
            'ul data-total' => in_array('rating', $classes, true)
                ? $this->matching(self::SCORE_PATTERN, $value)
                : null,
            'li data-score' => $this->matching(self::SCORE_PATTERN, $value),
            'figure data-layout', 'div data-layout' => $this->oneOf(self::ALLOWED_LAYOUTS, $value),
            'figure data-embed' => $this->oneOf(self::ALLOWED_EMBEDS, $value),
            'figure data-id' => $this->matching(self::EMBED_ID_PATTERN, $value),
            'div data-media' => in_array('gallery', $classes, true)
                ? $this->matching(self::GALLERY_MEDIA_PATTERN, $value)
                : null,
            'th colspan', 'th rowspan', 'td colspan', 'td rowspan' => $this->spanValue($value),
            'th scope' => $this->oneOf(self::ALLOWED_SCOPES, $value),
            'ol start' => $this->matching(self::LIST_START_PATTERN, $value),
            'time datetime' => $this->matching(self::DATETIME_PATTERN, $value),
            'details open' => '',
            default => null,
        };
    }

    private function matching(string $pattern, string $value): ?string
    {
        if (preg_match($pattern, $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(array $allowed, string $value): ?string
    {
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return null;
    }

    private function spanValue(string $value): ?string
    {
        if (preg_match(self::SPAN_PATTERN, $value) !== 1) {
            return null;
        }
        $number = (int) $value;
        if ($number < 1 || $number > self::SPAN_MAX) {
            return null;
        }

        return $value;
    }

    private function safeHref(string $value): ?string
    {
        $trimmed = trim($value, " \t\n\r\f\v\0");
        $probe = preg_replace('/[\x00-\x20\x7F]/', '', $trimmed) ?? '';

        if (str_starts_with($probe, '//')) {
            $this->note('Link mit unerlaubtem Ziel entfernt (//)');

            return null;
        }
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $probe, $match) === 1) {
            $scheme = strtolower($match[1]);
            if (!in_array($scheme, self::ALLOWED_LINK_SCHEMES, true)) {
                $this->note('Link mit unerlaubtem Ziel entfernt (' . $this->display($scheme) . ':)');

                return null;
            }
        }

        return $trimmed;
    }

    private function noteRemovedAttribute(string $element, string $attribute): void
    {
        // Den Hinweis für einen unerlaubten Link hat safeHref schon gesetzt.
        if ($element === 'a' && $attribute === 'href') {
            return;
        }

        $this->note('Attribut ' . $this->display($attribute) . ' an <' . $element . '> entfernt');
    }

    private function note(string $message): void
    {
        $this->notes[$message] = ($this->notes[$message] ?? 0) + 1;
    }

    /**
     * @return list<string>
     */
    private function collectNotes(): array
    {
        $result = [];
        foreach ($this->notes as $message => $count) {
            if ($count > 1) {
                $result[] = $count . '× ' . $message;
            } else {
                $result[] = $message;
            }
        }

        return $result;
    }

    /**
     * Namen stammen aus fremdem Markup; für Hinweise kürzen und von Steuerzeichen befreien.
     */
    private function display(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        return mb_substr($clean, 0, self::NAME_DISPLAY_LENGTH, 'UTF-8');
    }
}
