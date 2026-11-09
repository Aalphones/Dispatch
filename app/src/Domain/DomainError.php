<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use RuntimeException;

/** Fachlicher Fehler; die Meldung sagt, wie es richtig geht. MCP und API übersetzen ihn später. */
final class DomainError extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}
