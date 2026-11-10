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

/** Adresse einer Listenseite; Seite 1 hat nie `seite/1/`. */
function seitenUrl(string $basis, int $number): string
{
    if ($number === 1) {
        return $basis;
    }

    return $basis . 'seite/' . $number . '/';
}
