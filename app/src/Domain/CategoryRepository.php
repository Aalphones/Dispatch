<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class CategoryRepository
{
    /** Spalten, die insert/update schreiben dürfen — nur diese Namen landen im SQL. */
    private const WRITABLE = ['slug', 'name', 'description', 'parent_id', 'position', 'interest', 'keywords', 'avoid', 'active'];

    private const JSON_COLUMNS = ['keywords', 'avoid'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(bool $includeInactive): array
    {
        $sql = 'SELECT * FROM categories' . ($includeInactive ? '' : ' WHERE active = 1')
            . ' ORDER BY parent_id, position, name';

        return array_map(fn (array $row): array => $this->decode($row), $this->pdo->query($sql)->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM categories WHERE slug = ?');
        $statement->execute([$slug]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /** @param array<string, mixed> $fields vollständig: alle Spalten aus WRITABLE */
    public function insert(array $fields): int
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO categories (%s, created_at, updated_at) VALUES (%s, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        ));
        $statement->execute($this->encode($fields));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $assignments = array_map(static fn (string $column): string => $column . ' = ?', $columns);
        $statement = $this->pdo->prepare(
            'UPDATE categories SET ' . implode(', ', $assignments) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([...$this->encode($fields), $id]);
    }

    public function hasChildren(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categories WHERE parent_id = ? LIMIT 1');
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    /** @param list<string|int> $columns */
    private function assertWritable(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, self::WRITABLE, true)) {
                throw new \InvalidArgumentException('Unbekannte Kategorie-Spalte: ' . $column);
            }
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<mixed>
     */
    private function encode(array $fields): array
    {
        $values = [];
        foreach ($fields as $column => $value) {
            if (in_array($column, self::JSON_COLUMNS, true)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $row['position'] = (int) $row['position'];
        $row['interest'] = (int) $row['interest'];
        $row['active'] = (bool) $row['active'];
        foreach (self::JSON_COLUMNS as $column) {
            $row[$column] = json_decode((string) $row[$column], true, 512, JSON_THROW_ON_ERROR);
        }

        return $row;
    }
}
