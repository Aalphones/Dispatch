<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DocumentService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class DocumentTools
{
    private const KEYS = ['editorial_guidelines', 'preferences', 'about', 'imprint', 'privacy'];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var DocumentService $documents */
        $documents = $services['documents'];

        $registry->add(new Tool(
            'list_documents',
            'Dokumente auflisten',
            'Listet die fünf Dokumente (Redaktionsrichtlinien, Präferenzen und die öffentlichen Seiten) mit Schlüssel, Titel und Angabe, ob sie öffentlich sind. Den Inhalt liefert get_document.',
            Schema::object(),
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

        $registry->add(new Tool(
            'get_document',
            'Dokument lesen',
            'Liefert den vollständigen Inhalt eines Dokuments, etwa die Redaktionsrichtlinien vor dem Schreiben oder die Seite „Über“ vor einer Änderung. Beispiel: {"key": "editorial_guidelines"}. Gültige Schlüssel nennt list_documents; geändert wird mit update_document.',
            Schema::object(
                ['key' => Schema::choice(self::KEYS, 'Schlüssel des Dokuments')],
                ['key']
            ),
            Tool::annotations('Dokument lesen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($documents): ToolResult {
                $document = $documents->get((string) $args['key']);

                return ToolResult::json(self::present($document));
            }
        ));

        $registry->add(new Tool(
            'update_document',
            'Dokument ändern',
            'Ersetzt den Inhalt eines Dokuments. Richtlinien und Präferenzen gelten sofort und bekommen eine Revision; die öffentlichen Seiten about, imprint und privacy werden nur als Änderungsvorschlag angelegt, den der Inhaber freigibt (Ergebnis: change_id). Beispiel: {"key": "preferences", "content": "# Präferenzen …", "note": "Ton gestrafft"}. Danach prüft list_changes den Stand eines Vorschlags.',
            Schema::object([
                'key' => Schema::choice(self::KEYS, 'Schlüssel des Dokuments'),
                'content' => Schema::text('Neuer Inhalt als Markdown (das ganze Dokument, nicht nur die Änderung)'),
                'title' => Schema::text('Neuer Titel; ohne Angabe bleibt der alte'),
                'note' => Schema::text('Änderungsnotiz für die Revision, höchstens 500 Zeichen'),
            ], ['key', 'content']),
            Tool::annotations('Dokument ändern', 'W'),
            Scopes::ADMIN,
            static function (array $args, Actor $actor) use ($documents): ToolResult {
                $result = $documents->update(
                    (string) $args['key'],
                    (string) $args['content'],
                    isset($args['title']) ? (string) $args['title'] : null,
                    isset($args['note']) ? (string) $args['note'] : null,
                    $actor
                );

                return ToolResult::json($result);
            }
        ));
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private static function present(array $document): array
    {
        return [
            'key' => $document['doc_key'],
            'title' => $document['title'],
            'public' => in_array($document['doc_key'], DocumentService::PUBLIC_KEYS, true),
            'content' => $document['content'],
            'revision' => $document['revision'],
            'updated_at' => $document['updated_at'],
        ];
    }
}
