<?php

declare(strict_types=1);

namespace Dispatch\Site;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Datum für Leser: `10. November 2026`, kurz `10. Nov.`; Eingabe ist UTC, Ausgabe Berliner Zeit. */
function datum(string $utc, bool $kurz = false): string
{
    $formatter = new IntlDateFormatter(
        'de_DE',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        'Europe/Berlin',
        null,
        $kurz ? 'd. MMM' : 'd. MMMM y'
    );

    return (string) $formatter->format(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
}

/** Größte Fallback-Variante, die ausgeliefert wird; größere Dateien bleiben nur als srcset-Kandidat. */
const FALLBACK_MAX_WIDTH = 1200;

/**
 * Die Bildadressen eines Mediums, wie `<picture>` sie braucht. Einzige Stelle, die Varianten liest.
 *
 * @param array<string, mixed> $media MediaView
 * @return array{webp: string, fallback: string, src: string, w: int, h: int}
 */
function varianten(array $media): array
{
    $variants = array_values((array) $media['variants']);
    usort($variants, static fn (array $left, array $right): int => $left['w'] <=> $right['w']);

    $webp = [];
    $fallback = [];
    $src = $media['base_url'] . 'original.' . $media['ext'];
    $fallbackSrcWidth = null;
    foreach ($variants as $variant) {
        foreach ((array) $variant['files'] as $file) {
            $candidate = $media['base_url'] . $file . ' ' . $variant['w'] . 'w';
            if (str_ends_with((string) $file, '.webp')) {
                $webp[] = $candidate;
                continue;
            }
            $fallback[] = $candidate;
            // Das erste Fallback-Bild ist der Notnagel, falls alle Varianten über der Grenze liegen.
            if ($fallbackSrcWidth === null || $variant['w'] <= FALLBACK_MAX_WIDTH) {
                $fallbackSrcWidth = $variant['w'];
                $src = $media['base_url'] . $file;
            }
        }
    }

    $largest = $variants === [] ? (int) $media['width'] : (int) end($variants)['w'];
    $ratio = (int) $media['width'] > 0 ? (int) $media['height'] / (int) $media['width'] : 1.0;

    return [
        'webp' => implode(', ', $webp),
        'fallback' => implode(', ', $fallback),
        'src' => $src,
        'w' => $largest,
        'h' => (int) round($largest * $ratio),
    ];
}

/**
 * `<picture>` als HTML-Text für Karten, Titelbilder und Kategorie-Karten; alle Werte sind maskiert.
 *
 * @param array<string, mixed> $media MediaView
 */
function picture(array $media, string $sizes, bool $eager = false, string $class = ''): string
{
    $variants = varianten($media);
    $loading = $eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"';
    $classAttribute = $class === '' ? '' : ' class="' . e($class) . '"';

    $html = '<picture' . $classAttribute . '>';
    if ($variants['webp'] !== '') {
        $html .= '<source type="image/webp" srcset="' . e($variants['webp']) . '" sizes="' . e($sizes) . '">';
    }
    $html .= '<img src="' . e($variants['src']) . '"';
    if ($variants['fallback'] !== '') {
        $html .= ' srcset="' . e($variants['fallback']) . '" sizes="' . e($sizes) . '"';
    }

    return $html . ' width="' . $variants['w'] . '" height="' . $variants['h'] . '" alt="' . e((string) $media['alt'])
        . '" ' . $loading . ' decoding="async"></picture>';
}

/** Dachzeile über Titeln: das Stichwort des Artikels, sonst „Hauptkategorie · Unterkategorie“. */
function dachzeile(array $post): string
{
    $kicker = trim((string) $post['kicker']);
    if ($kicker !== '') {
        return $kicker;
    }

    $category = $post['primary_category'];
    if ($category === null) {
        return '';
    }
    $parentName = '';
    foreach ($post['categories'] as $candidate) {
        if ($candidate['slug'] === $category['parent_slug']) {
            $parentName = $candidate['name'] . ' · ';
        }
    }

    return $parentName . $category['name'];
}

/** „7 Quellen“ / „1 Quelle“. */
function quellenText(int $count): string
{
    return $count . ($count === 1 ? ' Quelle' : ' Quellen');
}

/** Adresse einer Listenseite; Seite 1 hat nie `seite/1/`. */
function seitenUrl(string $basis, int $number): string
{
    if ($number === 1) {
        return $basis;
    }

    return $basis . 'seite/' . $number . '/';
}
