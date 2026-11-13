<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * Kleiner Markdown-Umsetzer für die Dokumentseiten (ADR-014) und die Anweisungen für Agenten (ADR-031):
 * Überschriften, Absätze, Listen, Zitate, Trennlinie, fett, kursiv, Links, Code (Inline und Block) und
 * Tabellen. Alles andere bleibt Text.
 *
 * Aller Text wird zuerst maskiert; erst danach entstehen die wenigen erlaubten Tags. Das Ergebnis läuft
 * trotzdem durch den Sanitizer, damit ein Fehler hier nie ungeprüftes HTML ausliefert.
 */
final class Markdown
{
    /** `#` wird `h2`, weil die Seite selbst die `h1` trägt. */
    private const HEADING_OFFSET = 1;

    private const HEADING_PATTERN = '/^(#{1,4})[ \t]+(.+?)[ \t]*#*[ \t]*$/';
    private const RULE_PATTERN = '/^-{3,}[ \t]*$/';
    private const BULLET_PATTERN = '/^[-*][ \t]+(.*)$/';
    private const NUMBERED_PATTERN = '/^\d{1,9}\.[ \t]+(.*)$/';
    private const QUOTE_PATTERN = '/^>[ \t]?(.*)$/';
    private const LINK_PATTERN = '/\[([^\]\n]+)\]\(((?:[^()\s]|\([^()\s]*\))+)\)/';
    private const COMMENT_PATTERN = '/<!--.*?-->/s';
    private const FENCE_PATTERN = '/^```/';
    private const TABLE_ROW_PATTERN = '/^\|.*\|[ \t]*$/';
    private const TABLE_RULE_PATTERN = '/^\|?[ \t]*:?-{3,}:?[ \t]*(\|[ \t]*:?-{3,}:?[ \t]*)*\|?[ \t]*$/';
    private const CODE_PATTERN = '/`([^`\n]+)`/';

    private const PLACEHOLDER_START = "\x01";
    private const PLACEHOLDER_END = "\x02";

    public static function toHtml(string $markdown): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);
        $normalized = (string) preg_replace(self::COMMENT_PATTERN, '', $normalized);
        $normalized = str_replace([self::PLACEHOLDER_START, self::PLACEHOLDER_END], '', $normalized);

        $html = self::blocks(explode("\n", $normalized));

        return (new Sanitizer())->sanitize($html)->html;
    }

    /** @param list<string> $lines */
    private static function blocks(array $lines): string
    {
        $html = '';
        $paragraph = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = rtrim($lines[$index]);

            if (trim($line) === '') {
                $html .= self::paragraph($paragraph);
                $paragraph = [];

                continue;
            }

            if (preg_match(self::FENCE_PATTERN, $line) === 1) {
                $html .= self::paragraph($paragraph);
                $paragraph = [];
                [$fenceHtml, $index] = self::fence($lines, $index);
                $html .= $fenceHtml;

                continue;
            }

            if (self::startsTable($lines, $index)) {
                $html .= self::paragraph($paragraph);
                $paragraph = [];
                [$tableHtml, $index] = self::table($lines, $index);
                $html .= $tableHtml;

                continue;
            }

            $block = self::singleLineBlock($line);
            if ($block !== null) {
                $html .= self::paragraph($paragraph) . $block;
                $paragraph = [];

                continue;
            }

            $list = self::listType($line);
            if ($list !== null) {
                $html .= self::paragraph($paragraph);
                $paragraph = [];
                [$listHtml, $index] = self::listBlock($lines, $index, $list);
                $html .= $listHtml;

                continue;
            }

            if (preg_match(self::QUOTE_PATTERN, $line) === 1) {
                $html .= self::paragraph($paragraph);
                $paragraph = [];
                [$quoteHtml, $index] = self::quote($lines, $index);
                $html .= $quoteHtml;

                continue;
            }

            $paragraph[] = trim($line);
        }

        return $html . self::paragraph($paragraph);
    }

    private static function singleLineBlock(string $line): ?string
    {
        if (preg_match(self::HEADING_PATTERN, $line, $match) === 1) {
            $level = strlen($match[1]) + self::HEADING_OFFSET;

            return '<h' . $level . '>' . self::inline($match[2]) . '</h' . $level . '>';
        }
        if (preg_match(self::RULE_PATTERN, $line) === 1) {
            return '<hr>';
        }

        return null;
    }

    private static function listType(string $line): ?string
    {
        if (preg_match(self::BULLET_PATTERN, $line) === 1) {
            return 'ul';
        }
        if (preg_match(self::NUMBERED_PATTERN, $line) === 1) {
            return 'ol';
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return array{string, int} das HTML und der Index der letzten verbrauchten Zeile
     */
    private static function listBlock(array $lines, int $start, string $type): array
    {
        $pattern = $type === 'ul' ? self::BULLET_PATTERN : self::NUMBERED_PATTERN;
        $items = '';
        $index = $start;
        $count = count($lines);

        for (; $index < $count; $index++) {
            if (preg_match($pattern, rtrim($lines[$index]), $match) !== 1) {
                break;
            }
            $items .= '<li>' . self::inline($match[1]) . '</li>';
        }

        return ['<' . $type . '>' . $items . '</' . $type . '>', $index - 1];
    }

    /**
     * @param list<string> $lines
     * @return array{string, int} das HTML und der Index der letzten verbrauchten Zeile
     */
    private static function quote(array $lines, int $start): array
    {
        $quoted = [];
        $index = $start;
        $count = count($lines);

        for (; $index < $count; $index++) {
            if (preg_match(self::QUOTE_PATTERN, rtrim($lines[$index]), $match) !== 1) {
                break;
            }
            $quoted[] = trim($match[1]);
        }

        return ['<blockquote>' . self::blocks($quoted) . '</blockquote>', $index - 1];
    }

    /**
     * Ohne schließende ```-Zeile endet der Block am Textende. Die Sprachangabe hinter ``` zählt nicht.
     *
     * @param list<string> $lines
     * @return array{string, int} das HTML und der Index der letzten verbrauchten Zeile
     */
    private static function fence(array $lines, int $start): array
    {
        $code = [];
        $index = $start + 1;
        $count = count($lines);

        for (; $index < $count; $index++) {
            $line = rtrim($lines[$index]);
            if (preg_match(self::FENCE_PATTERN, $line) === 1) {
                return [self::codeBlock($code), $index];
            }
            $code[] = $line;
        }

        return [self::codeBlock($code), $count - 1];
    }

    /** @param list<string> $code */
    private static function codeBlock(array $code): string
    {
        return '<pre><code>' . self::escape(implode("\n", $code)) . '</code></pre>';
    }

    /** @param list<string> $lines */
    private static function startsTable(array $lines, int $index): bool
    {
        if (!isset($lines[$index + 1])) {
            return false;
        }

        return preg_match(self::TABLE_ROW_PATTERN, rtrim($lines[$index])) === 1
            && preg_match(self::TABLE_RULE_PATTERN, rtrim($lines[$index + 1])) === 1;
    }

    /**
     * Die Kopfzeile bestimmt die Spaltenzahl: Fehlende Zellen werden leer aufgefüllt, überzählige verworfen.
     *
     * @param list<string> $lines
     * @return array{string, int} das HTML und der Index der letzten verbrauchten Zeile
     */
    private static function table(array $lines, int $start): array
    {
        $headCells = self::cells($lines[$start]);
        $columnCount = count($headCells);
        $head = '';
        foreach ($headCells as $cell) {
            $head .= '<th scope="col">' . self::inline($cell) . '</th>';
        }

        $body = '';
        $index = $start + 2;
        $count = count($lines);
        for (; $index < $count; $index++) {
            $line = rtrim($lines[$index]);
            if (preg_match(self::TABLE_ROW_PATTERN, $line) !== 1) {
                break;
            }
            $rowCells = array_pad(array_slice(self::cells($line), 0, $columnCount), $columnCount, '');
            $body .= '<tr>' . implode('', array_map(
                static fn (string $cell): string => '<td>' . self::inline($cell) . '</td>',
                $rowCells
            )) . '</tr>';
        }

        return ['<table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>', $index - 1];
    }

    /**
     * Ein `|` innerhalb einer Zelle trennt immer — auch in Backticks; ein Escape gibt es nicht.
     *
     * @return list<string>
     */
    private static function cells(string $row): array
    {
        $inner = trim($row);
        if (str_starts_with($inner, '|')) {
            $inner = substr($inner, 1);
        }
        if (str_ends_with($inner, '|')) {
            $inner = substr($inner, 0, -1);
        }

        return array_map(trim(...), explode('|', $inner));
    }

    /** @param list<string> $lines */
    private static function paragraph(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        return '<p>' . implode('<br>', array_map(self::inline(...), $lines)) . '</p>';
    }

    /**
     * Code-Spannen und Links werden vor dem Maskieren durch Platzhalter ersetzt, damit Hervorhebung sie nicht
     * erreicht. Code kommt zuerst, damit ein `[x](y)` in Backticks Text bleibt.
     */
    private static function inline(string $text): string
    {
        $parts = [];
        $placeholder = static function (string $html) use (&$parts): string {
            $parts[] = $html;

            return self::PLACEHOLDER_START . (count($parts) - 1) . self::PLACEHOLDER_END;
        };

        $withCode = (string) preg_replace_callback(
            self::CODE_PATTERN,
            static fn (array $match): string => $placeholder('<code>' . self::escape($match[1]) . '</code>'),
            $text
        );
        $withLinks = (string) preg_replace_callback(
            self::LINK_PATTERN,
            static fn (array $match): string => $placeholder(self::link($match[1], $match[2])),
            $withCode
        );

        $html = self::emphasis(self::escape($withLinks));

        // Zweimal: Ein Link-Platzhalter kann einen Code-Platzhalter im Linktext enthalten.
        $replace = static fn (array $match): string => $parts[(int) $match[1]];
        $pattern = '/' . self::PLACEHOLDER_START . '(\d+)' . self::PLACEHOLDER_END . '/';
        $html = (string) preg_replace_callback($pattern, $replace, $html);

        return (string) preg_replace_callback($pattern, $replace, $html);
    }

    private static function link(string $label, string $address): string
    {
        $labelHtml = self::emphasis(self::escape($label));
        // Code in der Adresse ist ein Platzhalter und darf nie ins href-Attribut gelangen.
        if (str_contains($address, self::PLACEHOLDER_START) || !self::isAllowedAddress($address)) {
            return $labelHtml;
        }

        return '<a href="' . self::escape($address) . '">' . $labelHtml . '</a>';
    }

    private static function isAllowedAddress(string $address): bool
    {
        if (str_starts_with($address, '//')) {
            return false;
        }
        if (str_starts_with($address, '/')) {
            return true;
        }

        return (bool) preg_match('#^(https?://|mailto:)#i', $address);
    }

    private static function emphasis(string $escaped): string
    {
        $bold = (string) preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $escaped);

        return (string) preg_replace('/\*(?=\S)(.+?)(?<=\S)\*/', '<em>$1</em>', $bold);
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
