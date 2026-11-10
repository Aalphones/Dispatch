<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * Kleiner Markdown-Umsetzer für die Dokumentseiten (ADR-014): Überschriften, Absätze, Listen, Zitate,
 * Trennlinie, fett, kursiv, Links. Alles andere bleibt Text.
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

    /** @param list<string> $lines */
    private static function paragraph(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        return '<p>' . implode('<br>', array_map(self::inline(...), $lines)) . '</p>';
    }

    private static function inline(string $text): string
    {
        $links = [];
        $withPlaceholders = preg_replace_callback(
            self::LINK_PATTERN,
            static function (array $match) use (&$links): string {
                $links[] = self::link($match[1], $match[2]);

                return self::PLACEHOLDER_START . (count($links) - 1) . self::PLACEHOLDER_END;
            },
            $text
        );

        $html = self::emphasis(self::escape((string) $withPlaceholders));

        return (string) preg_replace_callback(
            '/' . self::PLACEHOLDER_START . '(\d+)' . self::PLACEHOLDER_END . '/',
            static fn (array $match): string => $links[(int) $match[1]],
            $html
        );
    }

    private static function link(string $label, string $address): string
    {
        $labelHtml = self::emphasis(self::escape($label));
        if (!self::isAllowedAddress($address)) {
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
