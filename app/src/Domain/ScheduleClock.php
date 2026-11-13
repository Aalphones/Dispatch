<?php

declare(strict_types=1);

namespace Dispatch\Domain;

/**
 * Schnellprüfung ohne Datenbank (ADR-028): Jede Anfrage liest nur diese Datei.
 * Löschen erzwingt eine Neuberechnung beim nächsten Auslöser.
 */
final class ScheduleClock
{
    public function __construct(private readonly string $storageDir)
    {
    }

    /** @return array{next_check: int, rebuild: bool}|null null = unbekannt oder unlesbar */
    public function read(): ?array
    {
        $path = $this->path();
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $data = $content === false ? null : json_decode($content, true);
        if (!is_array($data) || !isset($data['next_check']) || !is_int($data['next_check'])) {
            return null;
        }

        return ['next_check' => $data['next_check'], 'rebuild' => (bool) ($data['rebuild'] ?? false)];
    }

    public function write(int $nextCheck, bool $rebuild): void
    {
        $directory = dirname($this->path());
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Der Ordner ' . $directory . ' lässt sich nicht anlegen.');
        }

        file_put_contents($this->path(), json_encode(['next_check' => $nextCheck, 'rebuild' => $rebuild], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function forget(): void
    {
        if (is_file($this->path())) {
            unlink($this->path());
        }
    }

    private function path(): string
    {
        return $this->storageDir . '/schedule/next.json';
    }
}
