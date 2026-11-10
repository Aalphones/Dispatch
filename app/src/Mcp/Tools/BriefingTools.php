<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\BriefingService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class BriefingTools
{
    /** Ordner der Texte für Agenten (`app/mcp/`). */
    private const TEXT_DIRECTORY = __DIR__ . '/../../../mcp/';

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var BriefingService $briefing */
        $briefing = $services['briefing'];

        $registry->add(new Tool(
            'get_editorial_brief',
            'Redaktionsbriefing holen',
            'Startpunkt jedes Redaktionslaufs: liefert Richtlinien, Präferenzen, Kategorien mit Rotationswert, Formvorgaben, letzte Artikel, Leserinteresse, Reaktionen, Queue-Stand, zurückgegebene Entwürfe und im Feld workflow die Schritte für den gewählten Weg. Beispiel: {"mode": "auto"} wählt selbst (zurückgegebener Entwurf, dann Queue, dann geplantes Thema). Danach folgt je nach Weg claim_queue_item, get_post oder search_posts.',
            Schema::object([
                'mode' => Schema::choice(['auto', 'scheduled', 'queue'], 'Weg; Standard auto'),
            ]),
            Tool::annotations('Redaktionsbriefing holen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($briefing): ToolResult {
                $result = $briefing->brief((string) ($args['mode'] ?? 'auto'));
                $result['workflow'] = self::text('workflow-' . $result['mode'] . '.md');

                return ToolResult::json($result);
            }
        ));

        $registry->add(new Tool(
            'get_style_guide',
            'Style-Guide holen',
            'Liefert den Style-Guide als Markdown: erlaubte HTML-Elemente und Klassen, Medien- und Belegsyntax, Layouts, Akzentfarbe und die Pflichtangaben der Formprüfung. Aufruf ohne Argumente: {}. Lies ihn vor dem ersten create_post eines Laufs.',
            Schema::object(),
            Tool::annotations('Style-Guide holen', 'R'),
            Scopes::READ,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json(['style_guide' => self::text('style-guide.md')])
        ));
    }

    private static function text(string $file): string
    {
        return trim((string) file_get_contents(self::TEXT_DIRECTORY . $file));
    }
}
