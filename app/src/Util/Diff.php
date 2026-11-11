<?php

declare(strict_types=1);

namespace Dispatch\Util;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Änderungsansicht zweier Fassungen eines Artikels (ADR-021): Vergleich der direkten Kindelemente (Blöcke), in
 * geänderten Textblöcken wortweise. Ein Vergleich über das ganze HTML würde Tags zerreißen.
 *
 * Die Eingaben sind schon bereinigt (Sanitizer); die Ausgabe enthält nur deren Elemente plus `ins`, `del` und
 * die Klassen `diff-weg` / `diff-neu`.
 */
final class Diff
{
    private const MAX_BLOCKS = 400;
    private const MAX_WORDS = 2000;

    private const REMOVED_CLASS = 'diff-weg';
    private const ADDED_CLASS = 'diff-neu';

    /** Nur Elemente, deren Inhalt als Fließtext dargestellt werden kann; Listen, Tabellen, Figuren zerfielen dabei. */
    private const PAIRABLE_TAGS = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'div', 'aside', 'section'];

    private const TEXT_TAG = '#text';

    /**
     * @return array{html: string, blocks_changed: int}
     */
    public static function html(string $old, string $new): array
    {
        $oldBlocks = self::blocks($old);
        $newBlocks = self::blocks($new);

        if (count($oldBlocks) > self::MAX_BLOCKS || count($newBlocks) > self::MAX_BLOCKS) {
            return self::replaceAll($oldBlocks, $newBlocks);
        }

        $matches = self::commonPairs(
            array_column($oldBlocks, 'signature'),
            array_column($newBlocks, 'signature')
        );
        $matches[] = [count($oldBlocks), count($newBlocks)];

        $parts = [];
        $changed = 0;
        $oldPosition = 0;
        $newPosition = 0;
        foreach ($matches as [$oldMatch, $newMatch]) {
            $run = self::renderRun(
                array_slice($oldBlocks, $oldPosition, $oldMatch - $oldPosition),
                array_slice($newBlocks, $newPosition, $newMatch - $newPosition)
            );
            array_push($parts, ...$run['parts']);
            $changed += $run['changed'];

            if ($newMatch < count($newBlocks)) {
                $parts[] = $newBlocks[$newMatch]['html'];
            }
            $oldPosition = $oldMatch + 1;
            $newPosition = $newMatch + 1;
        }

        return ['html' => implode("\n", $parts), 'blocks_changed' => $changed];
    }

    /**
     * @param list<array{tag: string, open: string, html: string, text: string, signature: string}> $oldBlocks
     * @param list<array{tag: string, open: string, html: string, text: string, signature: string}> $newBlocks
     * @return array{html: string, blocks_changed: int}
     */
    private static function replaceAll(array $oldBlocks, array $newBlocks): array
    {
        $parts = [];
        foreach ($oldBlocks as $block) {
            $parts[] = self::marked($block['html'], self::REMOVED_CLASS);
        }
        foreach ($newBlocks as $block) {
            $parts[] = self::marked($block['html'], self::ADDED_CLASS);
        }

        return ['html' => implode("\n", $parts), 'blocks_changed' => count($parts)];
    }

    /**
     * Zwischen zwei gleichen Blöcken: entfernte und neue Blöcke paarweise zuordnen, solange der Tag gleich ist.
     *
     * @param list<array{tag: string, open: string, html: string, text: string, signature: string}> $removed
     * @param list<array{tag: string, open: string, html: string, text: string, signature: string}> $added
     * @return array{parts: list<string>, changed: int}
     */
    private static function renderRun(array $removed, array $added): array
    {
        $parts = [];
        $paired = 0;
        $limit = min(count($removed), count($added));
        while ($paired < $limit && self::isPairable($removed[$paired], $added[$paired])) {
            $parts[] = self::mergedBlock($removed[$paired], $added[$paired]);
            $paired++;
        }

        for ($index = $paired; $index < count($removed); $index++) {
            $parts[] = self::marked($removed[$index]['html'], self::REMOVED_CLASS);
        }
        for ($index = $paired; $index < count($added); $index++) {
            $parts[] = self::marked($added[$index]['html'], self::ADDED_CLASS);
        }

        return ['parts' => $parts, 'changed' => count($removed) + count($added) - $paired];
    }

    /**
     * @param array{tag: string, text: string} $removed
     * @param array{tag: string, text: string} $added
     */
    private static function isPairable(array $removed, array $added): bool
    {
        return $removed['tag'] === $added['tag']
            && in_array($removed['tag'], self::PAIRABLE_TAGS, true)
            && $removed['text'] !== $added['text'];
    }

    /**
     * Der neue Block mit Wortvergleich; Auszeichnung innerhalb des Blocks entfällt bewusst.
     *
     * @param array{tag: string, open: string, html: string, text: string} $removed
     * @param array{tag: string, open: string, html: string, text: string} $added
     */
    private static function mergedBlock(array $removed, array $added): string
    {
        $oldWords = self::words($removed['text']);
        $newWords = self::words($added['text']);

        // Der Wortvergleich wächst quadratisch; über dem Deckel wird der Block ganz ersetzt.
        if (count($oldWords) > self::MAX_WORDS || count($newWords) > self::MAX_WORDS) {
            return self::marked($removed['html'], self::REMOVED_CLASS) . "\n" . self::marked($added['html'], self::ADDED_CLASS);
        }

        $matches = self::commonPairs($oldWords, $newWords);
        $matches[] = [count($oldWords), count($newWords)];

        $tokens = [];
        $oldPosition = 0;
        $newPosition = 0;
        foreach ($matches as [$oldMatch, $newMatch]) {
            if ($oldMatch > $oldPosition) {
                $tokens[] = '<del>' . self::escape(implode(' ', array_slice($oldWords, $oldPosition, $oldMatch - $oldPosition))) . '</del>';
            }
            if ($newMatch > $newPosition) {
                $tokens[] = '<ins>' . self::escape(implode(' ', array_slice($newWords, $newPosition, $newMatch - $newPosition))) . '</ins>';
            }
            if ($newMatch < count($newWords)) {
                $tokens[] = self::escape($newWords[$newMatch]);
            }
            $oldPosition = $oldMatch + 1;
            $newPosition = $newMatch + 1;
        }

        return '<' . $added['tag'] . $added['open'] . '>' . implode(' ', $tokens) . '</' . $added['tag'] . '>';
    }

    /**
     * Längste gemeinsame Teilfolge als Liste von Positionspaaren (alt, neu), aufsteigend.
     *
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{int, int}>
     */
    private static function commonPairs(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);

        $start = 0;
        while ($start < $oldCount && $start < $newCount && $old[$start] === $new[$start]) {
            $start++;
        }
        $oldEnd = $oldCount;
        $newEnd = $newCount;
        while ($oldEnd > $start && $newEnd > $start && $old[$oldEnd - 1] === $new[$newEnd - 1]) {
            $oldEnd--;
            $newEnd--;
        }

        $pairs = [];
        for ($index = 0; $index < $start; $index++) {
            $pairs[] = [$index, $index];
        }
        foreach (self::middlePairs($old, $new, $start, $oldEnd, $newEnd) as $pair) {
            $pairs[] = $pair;
        }
        for ($offset = 0; $offset < $oldCount - $oldEnd; $offset++) {
            $pairs[] = [$oldEnd + $offset, $newEnd + $offset];
        }

        return $pairs;
    }

    /**
     * @param list<string> $old
     * @param list<string> $new
     * @return list<array{int, int}>
     */
    private static function middlePairs(array $old, array $new, int $start, int $oldEnd, int $newEnd): array
    {
        $oldLength = $oldEnd - $start;
        $newLength = $newEnd - $start;
        if ($oldLength === 0 || $newLength === 0) {
            return [];
        }

        // lengths[i][j] = Länge der längsten gemeinsamen Teilfolge der Reste ab i bzw. j.
        $lengths = array_fill(0, $oldLength + 1, array_fill(0, $newLength + 1, 0));
        for ($row = $oldLength - 1; $row >= 0; $row--) {
            for ($column = $newLength - 1; $column >= 0; $column--) {
                if ($old[$start + $row] === $new[$start + $column]) {
                    $lengths[$row][$column] = $lengths[$row + 1][$column + 1] + 1;
                } else {
                    $lengths[$row][$column] = max($lengths[$row + 1][$column], $lengths[$row][$column + 1]);
                }
            }
        }

        $pairs = [];
        $row = 0;
        $column = 0;
        while ($row < $oldLength && $column < $newLength) {
            if ($old[$start + $row] === $new[$start + $column]) {
                $pairs[] = [$start + $row, $start + $column];
                $row++;
                $column++;
            } elseif ($lengths[$row + 1][$column] >= $lengths[$row][$column + 1]) {
                $row++;
            } else {
                $column++;
            }
        }

        return $pairs;
    }

    /**
     * Die direkten Kindelemente (und lose Textstücke) des Artikel-HTML.
     *
     * @return list<array{tag: string, open: string, html: string, text: string, signature: string}>
     */
    private static function blocks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_NOERROR,
            'UTF-8'
        );
        $body = $document->body;
        if ($body === null) {
            return [];
        }

        $blocks = [];
        foreach ($body->childNodes as $node) {
            if ($node instanceof Element) {
                $blocks[] = self::elementBlock($document->saveHtml($node), $node);
            } elseif ($node->nodeType === XML_TEXT_NODE && trim((string) $node->textContent) !== '') {
                $text = trim((string) preg_replace('/\s+/u', ' ', (string) $node->textContent));
                $blocks[] = [
                    'tag' => self::TEXT_TAG,
                    'open' => '',
                    'html' => self::escape($text),
                    'text' => $text,
                    'signature' => self::TEXT_TAG . "\0" . $text,
                ];
            }
        }

        return $blocks;
    }

    /**
     * @return array{tag: string, open: string, html: string, text: string, signature: string}
     */
    private static function elementBlock(string $html, Element $element): array
    {
        $tag = strtolower($element->localName);
        $text = Text::plain($html);

        $open = '';
        foreach ($element->attributes as $attribute) {
            $open .= ' ' . $attribute->name . '="' . self::escape($attribute->value) . '"';
        }

        // Ohne Text (Abbildung, Galerie, Trennlinie) unterscheidet nur das Markup zwei Blöcke.
        $identity = $text === '' ? $html : $text;

        return [
            'tag' => $tag,
            'open' => $open,
            'html' => $html,
            'text' => $text,
            'signature' => $tag . "\0" . $identity,
        ];
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode(' ', $text);
    }

    /** Das Wurzelelement des Blocks bekommt die Klasse; das Original bleibt als Inhalt unangetastet. */
    private static function marked(string $html, string $class): string
    {
        return '<div class="' . $class . '">' . $html . '</div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
