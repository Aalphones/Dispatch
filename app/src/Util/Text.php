<?php

declare(strict_types=1);

namespace Dispatch\Util;

use RuntimeException;

final class Text
{
    private const SLUG_MAX_LENGTH = 80;
    private const SLUG_FALLBACK = 'artikel';

    private const GERMAN_UMLAUTS = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
    ];

    /** Elemente, die Wörter trennen — ohne Leerraum würden „</p><p>“ zwei Wörter verschmelzen. */
    private const BLOCK_TAG_PATTERN = '#</?(?:p|h[1-6]|ul|ol|li|dl|dt|dd|table|caption|thead|tbody|tfoot|tr|th|td|figure|figcaption|aside|section|details|summary|blockquote|div|br|hr)\b[^>]*>#i';

    private const WORD_PATTERN = '/[\p{L}\p{N}]+(?:[\'’\-][\p{L}\p{N}]+)*/u';

    public static function wordCount(string $html): int
    {
        return (int) preg_match_all(self::WORD_PATTERN, self::plain($html));
    }

    /** Klartext für Suche und Zählung: Tags entfernt, Entities dekodiert, Leerraum zusammengefasst. */
    public static function plain(string $html): string
    {
        $separated = (string) preg_replace(self::BLOCK_TAG_PATTERN, ' ', $html);
        $text = html_entity_decode(strip_tags($separated), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public static function slugify(string $text): string
    {
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        if ($transliterator === null) {
            throw new RuntimeException('Die intl-Erweiterung kann nicht transliterieren.');
        }

        $ascii = (string) $transliterator->transliterate(strtr($text, self::GERMAN_UMLAUTS));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
        $slug = self::truncateAtWordBoundary($slug, self::SLUG_MAX_LENGTH);

        return $slug === '' ? self::SLUG_FALLBACK : $slug;
    }

    private static function truncateAtWordBoundary(string $slug, int $maxLength): string
    {
        if (strlen($slug) <= $maxLength) {
            return $slug;
        }

        $cut = substr($slug, 0, $maxLength);
        if ($slug[$maxLength] !== '-') {
            $lastDash = strrpos($cut, '-');
            if ($lastDash !== false && $lastDash > 0) {
                $cut = substr($cut, 0, $lastDash);
            }
        }

        return trim($cut, '-');
    }
}
