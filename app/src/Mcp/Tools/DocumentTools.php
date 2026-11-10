<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DocumentService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class DocumentTools
{
    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var DocumentService $documents */
        $documents = $services['documents'];

        $registry->add(new Tool(
            'list_documents',
            'Dokumente auflisten',
            'Listet die fünf Dokumente (Redaktionsrichtlinien, Präferenzen und die öffentlichen Seiten) mit Schlüssel, Titel und Angabe, ob sie öffentlich sind. Den Inhalt liefert get_document.',
            ['type' => 'object', 'additionalProperties' => false],
            Tool::annotations('Dokumente auflisten', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($documents): ToolResult {
                $entries = [];
                foreach ($documents->list() as $document) {
                    $entries[] = [
                        'key' => $document['doc_key'],
                        'title' => $document['title'],
                        'public' => in_array($document['doc_key'], DocumentService::PUBLIC_KEYS, true),
                        'revision' => $document['revision'],
                        'updated_at' => $document['updated_at'],
                    ];
                }

                return ToolResult::json(['documents' => $entries]);
            }
        ));
    }
}
