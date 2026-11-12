<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use LogicException;

/** Einzige Quelle für `tools/list`, Scope-Prüfung und Argumentprüfung. */
final class Registry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function add(Tool $tool): void
    {
        if (isset($this->tools[$tool->name])) {
            throw new LogicException(sprintf('Tool %s ist schon registriert.', $tool->name));
        }
        $this->tools[$tool->name] = $tool;
    }

    /**
     * @param list<string> $scopes Scopes des Tokens
     * @return list<Tool>
     */
    public function visible(array $scopes): array
    {
        return array_values(array_filter(
            $this->tools,
            static fn (Tool $tool): bool => in_array($tool->scope, $scopes, true)
        ));
    }

    /**
     * @param array<string, mixed> $args
     * @param array{name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     */
    public function call(string $name, array $args, array $auth): ToolResult
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            throw new ProtocolError(ProtocolError::INVALID_PARAMS, sprintf('Unbekanntes Tool „%s“. tools/list zeigt die verfügbaren.', $name));
        }
        if (!in_array($tool->scope, $auth['scopes'], true)) {
            $message = sprintf('Das Tool „%s“ braucht den Scope %s; das Token hat ihn nicht.', $name, $tool->scope);
            throw new ProtocolError(
                ProtocolError::INSUFFICIENT_SCOPE,
                $message,
                403,
                ['required_scope' => $tool->scope],
                ['WWW-Authenticate' => sprintf('Bearer error="insufficient_scope", scope="%s"', $tool->scope)]
            );
        }

        $problem = self::argumentProblem($tool, $args);
        if ($problem !== null) {
            return ToolResult::error('invalid_argument', $problem['message'], ['field' => $problem['field']]);
        }

        try {
            $actor = $auth['kind'] === 'oauth' ? Actor::oauth($auth['name']) : Actor::mcp($auth['name']);

            return ($tool->handler)($args, $actor);
        } catch (DomainError $error) {
            return ToolResult::error($error->errorCode, $error->getMessage(), $error->details);
        }
    }

    /**
     * Prüft Pflichtargumente und Grundtypen; tiefer prüfen die Services.
     *
     * @param array<string, mixed> $args
     * @return array{field: string, message: string}|null
     */
    private static function argumentProblem(Tool $tool, array $args): ?array
    {
        $properties = $tool->inputSchema['properties'] ?? [];
        $required = $tool->inputSchema['required'] ?? [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $args) || $args[$field] === null) {
                return [
                    'field' => $field,
                    'message' => sprintf('Argument „%s“ fehlt (%s). Beispiel: %s', $field, self::typeLabel($properties[$field]['type'] ?? 'string'), self::example($field, $properties[$field]['type'] ?? 'string')),
                ];
            }
        }
        foreach ($args as $field => $value) {
            $type = $properties[$field]['type'] ?? null;
            if ($value === null || $type === null || self::matchesType($type, $value)) {
                continue;
            }

            return [
                'field' => (string) $field,
                'message' => sprintf('Argument „%s“ muss %s sein. Beispiel: %s', $field, self::typeLabel($type), self::example((string) $field, $type)),
            ];
        }

        return null;
    }

    private static function matchesType(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            // Ein leeres JSON-Objekt und eine leere Liste sind in PHP beide `[]`.
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            default => true,
        };
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'string' => 'ein Text',
            'integer' => 'eine ganze Zahl',
            'number' => 'eine Zahl',
            'boolean' => 'true oder false',
            'array' => 'eine Liste',
            'object' => 'ein Objekt',
            default => $type,
        };
    }

    private static function example(string $field, string $type): string
    {
        $value = match ($type) {
            'integer' => '1',
            'number' => '1.5',
            'boolean' => 'true',
            'array' => '["…"]',
            'object' => '{"…": "…"}',
            default => '"…"',
        };

        return sprintf('{"%s": %s}', $field, $value);
    }
}
