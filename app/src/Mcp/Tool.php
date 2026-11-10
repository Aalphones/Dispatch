<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Closure;
use Dispatch\Auth\Scopes;
use LogicException;

/** Ein MCP-Tool; die Registry ist die einzige Quelle für `tools/list` und die Scope-Prüfung. */
final readonly class Tool
{
    private const REQUIRED_ANNOTATIONS = ['title', 'readOnlyHint', 'destructiveHint', 'idempotentHint', 'openWorldHint'];

    /**
     * @param array<string, mixed> $inputSchema JSON-Schema-Objekt
     * @param array<string, mixed> $annotations title + die vier Hints, alle Pflicht
     * @param Closure(array<string, mixed>, \Dispatch\Domain\Actor): ToolResult $handler
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public array $inputSchema,
        public array $annotations,
        public string $scope,
        public Closure $handler
    ) {
        if (!in_array($scope, Scopes::ALL, true)) {
            throw new LogicException(sprintf('Tool %s: unbekannter Scope „%s“.', $name, $scope));
        }
        foreach (self::REQUIRED_ANNOTATIONS as $key) {
            if (!array_key_exists($key, $annotations)) {
                throw new LogicException(sprintf('Tool %s: Annotation „%s“ fehlt.', $name, $key));
            }
        }
        if (($inputSchema['type'] ?? null) !== 'object') {
            throw new LogicException(sprintf('Tool %s: inputSchema muss ein Objekt-Schema sein.', $name));
        }
    }

    /**
     * Annotationen nach Kürzel des Katalogs: R lesend, W schreibend, D zerstörend, O offene Welt.
     *
     * @return array<string, mixed>
     */
    public static function annotations(string $title, string $flags): array
    {
        $isReadOnly = str_contains($flags, 'R');

        return [
            'title' => $title,
            'readOnlyHint' => $isReadOnly,
            'destructiveHint' => str_contains($flags, 'D'),
            'idempotentHint' => $isReadOnly,
            'openWorldHint' => str_contains($flags, 'O'),
        ];
    }

    /** @return array<string, mixed> */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
            'annotations' => $this->annotations,
        ];
    }
}
