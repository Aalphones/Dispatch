<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class SettingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $values = [];
        foreach ($this->pdo->query('SELECT name, value FROM settings')->fetchAll() as $row) {
            $values[(string) $row['name']] = json_decode((string) $row['value'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $values;
    }

    /** Fehlend → null. */
    public function get(string $name): mixed
    {
        $statement = $this->pdo->prepare('SELECT value FROM settings WHERE name = ?');
        $statement->execute([$name]);
        $value = $statement->fetchColumn();
        if ($value === false) {
            return null;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function upsert(string $name, mixed $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            $name,
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    public function exists(string $name): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM settings WHERE name = ?');
        $statement->execute([$name]);

        return $statement->fetchColumn() !== false;
    }
}
