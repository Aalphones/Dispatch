<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * Ergebnis eines Sanitizer-Laufs: bereinigtes HTML und deutsche Hinweise, was entfernt oder umgewandelt wurde.
 */
final readonly class SanitizeResult
{
    /**
     * @param list<string> $removed
     */
    public function __construct(
        public string $html,
        public array $removed,
    ) {
    }
}
