<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

/** Drei Prompts, die einen Redaktionslauf anstoßen (Slash-Befehle in Claude Code). */
final class Prompts
{
    private const TEXT_DIRECTORY = __DIR__ . '/../../mcp/';

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return [
            ['name' => 'redaktion_geplant', 'title' => 'Geplanter Artikel', 'description' => 'Schreibt einen Artikel aus eigener Themenwahl nach dem Redaktionsbriefing.', 'arguments' => []],
            ['name' => 'redaktion_queue', 'title' => 'Auftrag aus der Queue', 'description' => 'Reserviert den nächsten Queue-Auftrag und schreibt den Artikel dazu.', 'arguments' => []],
            [
                'name' => 'thema_einreichen',
                'title' => 'Thema einreichen',
                'description' => 'Legt ein Thema als Auftrag auf die Queue.',
                'arguments' => [['name' => 'thema', 'description' => 'Das Thema, gern mit Priorität und Tiefe', 'required' => true]],
            ],
        ];
    }

    /**
     * Prompts mit ihrem Text für die Ansicht im Redaktionsmodus; das Thema ist ein Platzhalter.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_map(
            function (array $prompt): array {
                $arguments = [];
                if ($prompt['name'] === 'thema_einreichen') {
                    $arguments = ['thema' => '‹Thema›'];
                }
                $prompt['text'] = $this->get($prompt['name'], $arguments)['messages'][0]['content']['text'];

                return $prompt;
            },
            $this->list()
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{description: string, messages: list<array{role: string, content: array{type: string, text: string}}>}
     */
    public function get(string $name, array $arguments): array
    {
        [$description, $text] = match ($name) {
            'redaktion_geplant' => ['Geplanter Artikel', self::editorial('scheduled')],
            'redaktion_queue' => ['Auftrag aus der Queue', self::editorial('queue')],
            'thema_einreichen' => ['Thema einreichen', self::submission($arguments)],
            default => throw new ProtocolError(ProtocolError::INVALID_PARAMS, sprintf('Prompt „%s“ ist unbekannt. prompts/list nennt die gültigen.', $name), 400, ['name' => $name]),
        };

        return ['description' => $description, 'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]];
    }

    private static function editorial(string $mode): string
    {
        $workflow = trim((string) file_get_contents(self::TEXT_DIRECTORY . 'workflow-' . $mode . '.md'));

        return sprintf("Rufe get_editorial_brief(mode=\"%s\") auf und folge dem Workflow.\n\n%s", $mode, $workflow);
    }

    /** @param array<string, mixed> $arguments */
    private static function submission(array $arguments): string
    {
        $topic = $arguments['thema'] ?? null;
        if (!is_string($topic) || trim($topic) === '') {
            throw new ProtocolError(ProtocolError::INVALID_PARAMS, 'Der Prompt „thema_einreichen“ braucht das Argument „thema“ (Text), z. B. {"thema": "Neue Quantenstudie"}.', 400, ['argument' => 'thema']);
        }

        return sprintf('Setze dieses Thema mit add_to_queue auf die Queue: %s. Frage nach Priorität und Tiefe nur, wenn der Text sie nicht nennt.', trim($topic));
    }
}
