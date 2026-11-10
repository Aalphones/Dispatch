<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

/** Kurzschreibweise für die JSON-Schemas der Tool-Eingaben. */
final class Schema
{
    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    public static function object(array $properties = [], array $required = []): array
    {
        $schema = ['type' => 'object', 'additionalProperties' => false];
        if ($properties !== []) {
            $schema['properties'] = $properties;
        }
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @return array<string, string> */
    public static function text(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /** @return array<string, string> */
    public static function integer(string $description): array
    {
        return ['type' => 'integer', 'description' => $description];
    }

    /** @return array<string, string> */
    public static function boolean(string $description): array
    {
        return ['type' => 'boolean', 'description' => $description];
    }

    /**
     * @param list<string> $values
     * @return array<string, mixed>
     */
    public static function choice(array $values, string $description): array
    {
        return ['type' => 'string', 'enum' => $values, 'description' => $description];
    }

    /**
     * @param array<string, mixed> $items
     * @return array<string, mixed>
     */
    public static function list(array $items, string $description): array
    {
        return ['type' => 'array', 'items' => $items, 'description' => $description];
    }
}
