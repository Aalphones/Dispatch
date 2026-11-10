<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * Ergebnis der Formprüfung. Einträge: {code, field, message}.
 *
 * @phpstan-type Finding array{code: string, field: string, message: string}
 */
final readonly class FormCheckResult
{
    /**
     * @param list<array{code: string, field: string, message: string}> $errors
     * @param list<array{code: string, field: string, message: string}> $warnings
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public array $metrics
    ) {
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }

    /** @return array{ok: bool, errors: list<array<string, string>>, warnings: list<array<string, string>>, metrics: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }

    /** @return list<string> alle Codes (Fehler und Warnungen) */
    public function codes(): array
    {
        return array_map(
            static fn (array $finding): string => $finding['code'],
            [...$this->errors, ...$this->warnings]
        );
    }
}
