<?php

declare(strict_types=1);

namespace Dispatch\Site;

use RuntimeException;

final class Manifest
{
    public function __construct(private readonly string $file)
    {
    }

    /** @return list<string> */
    public function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($decoded) || !is_array($decoded['files'] ?? null)) {
            return [];
        }

        return array_values(array_filter($decoded['files'], 'is_string'));
    }

    /** @param list<string> $files */
    public function write(array $files): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Manifest-Ordner nicht anlegbar.');
        }

        $json = json_encode(
            ['built_at' => gmdate('c'), 'files' => $files],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );

        $temporaryFile = $this->file . '.tmp';
        if (file_put_contents($temporaryFile, $json, LOCK_EX) === false) {
            throw new RuntimeException('Manifest nicht schreibbar.');
        }
        if (!rename($temporaryFile, $this->file)) {
            throw new RuntimeException('Manifest nicht ersetzbar.');
        }
    }
}
