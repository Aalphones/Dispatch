<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * Ergebnis eines CssSanitizer-Laufs: bereinigtes CSS (eine Regel je Zeile) und deutsche Hinweise, was entfernt wurde.
 */
final readonly class CssResult
{
    /**
     * @param list<string> $removed
     */
    public function __construct(
        public string $css,
        public array $removed,
    ) {
    }
}
