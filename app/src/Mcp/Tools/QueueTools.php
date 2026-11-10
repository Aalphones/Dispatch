<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaService;
use Dispatch\Domain\QueueService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class QueueTools
{
    private const STATUSES = ['open', 'claimed', 'review', 'done', 'failed', 'cancelled'];
    private const RELEASE_STATUSES = ['open', 'failed'];
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;
    private const ATTACHMENTS_MAX = 10;
    private const MEDIA_META_FIELDS = ['alt', 'caption', 'credit', 'license', 'license_url', 'source_url'];
    private const ATTACHMENT_SOURCES = ['url', 'data_base64', 'uid'];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var QueueService $queue */
        $queue = $services['queue'];
        /** @var MediaService $media */
        $media = $services['media'];

        $registry->add(new Tool(
            'list_queue',
            'Queue auflisten',
            'Listet Aufträge der Queue, standardmäßig offene, reservierte und eingereichte. Nutze es, um vor add_to_queue Dubletten zu erkennen oder den Stand eines Auftrags zu sehen. Beispiel: {"status": ["open"], "limit": 10}. Zum Bearbeiten folgt claim_queue_item.',
            Schema::object([
                'status' => Schema::list(Schema::choice(self::STATUSES, 'Status'), 'Gewünschte Status; Standard open, claimed, review'),
                'limit' => Schema::integer('Höchstens so viele Aufträge, 1 bis 100; Standard 50'),
                'offset' => Schema::integer('Überspringt so viele Aufträge; Standard 0'),
            ]),
            Tool::annotations('Queue auflisten', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($queue): ToolResult {
                $items = $queue->list(
                    array_map('strval', $args['status'] ?? ['open', 'claimed', 'review']),
                    self::limit($args),
                    max(0, (int) ($args['offset'] ?? 0))
                );

                return ToolResult::json(['items' => $items]);
            }
        ));

        $registry->add(new Tool(
            'add_to_queue',
            'Thema in die Queue legen',
            'Legt ein Thema als Auftrag in die Queue, damit ein späterer Lauf es als Artikel schreibt. Anhänge (Fotos, Material) werden vorher als Medien angelegt: je Anhang genau eines von url (Import, source_url = url), data_base64 mit filename oder uid (vorhandenes Medium), optional own_material, note und Nachweise (alt, credit, license, …); ein fehlerhafter Anhang bricht ab, bevor der Auftrag entsteht. Beispiel: {"title": "Neue Quantenstudie", "brief": "Fokus auf Methodik", "attachments": [{"url": "https://example.org/bild.jpg"}]}. Danach list_queue oder, zum Bearbeiten, claim_queue_item.',
            Schema::object([
                'title' => Schema::text('Arbeitstitel des Themas, 1 bis 200 Zeichen'),
                'brief' => Schema::text('Auftragstext: Blickwinkel, Fragen, Vorgaben'),
                'links' => Schema::list(['type' => 'string'], 'Web-Adressen mit Material (http/https)'),
                'attachments' => Schema::list(self::attachmentSchema(), 'Anhänge; höchstens ' . self::ATTACHMENTS_MAX),
                'priority' => Schema::integer('1 (am wichtigsten) bis 3; Standard 2'),
                'depth' => Schema::choice(['standard', 'deep'], 'Recherchetiefe; Standard standard'),
                'category' => Schema::text('Slug einer aktiven Kategorie (list_categories)'),
            ], ['title']),
            Tool::annotations('Thema in die Queue legen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($queue, $media): ToolResult {
                $input = array_intersect_key($args, array_flip(['title', 'brief', 'links', 'priority', 'depth', 'category']));
                $input['media'] = self::resolveAttachments($args['attachments'] ?? [], $media, $actor);

                return ToolResult::json(['item' => $queue->add($input, $actor)]);
            }
        ));

        $registry->add(new Tool(
            'claim_queue_item',
            'Auftrag reservieren',
            'Reserviert einen Auftrag für die eingestellte Frist (Standard 180 Minuten) und liefert Titel, Auftragstext, Links und die angehängten Medien. Ohne id kommt der wichtigste und älteste offene Auftrag, mit id genau dieser. Beispiel: {"id": 7}. Danach recherchieren und mit create_post (queue_item_id) schreiben; gelingt das nicht, gibt release_queue_item ihn zurück.',
            Schema::object(['id' => Schema::integer('Nummer des Auftrags; ohne Angabe der nächste offene')]),
            Tool::annotations('Auftrag reservieren', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($queue): ToolResult {
                $item = $queue->claim(isset($args['id']) ? (int) $args['id'] : null, $actor);

                return ToolResult::json(['item' => $item]);
            }
        ));

        $registry->add(new Tool(
            'release_queue_item',
            'Auftrag zurückgeben',
            'Gibt einen reservierten Auftrag zurück: open legt ihn wieder zur Vergabe frei, failed bricht ihn mit Begründung ab (note ist dann Pflicht). Gibt es schon einen Artikel zum Auftrag, verwirf ihn zuerst mit delete_post. Beispiel: {"id": 7, "status": "failed", "note": "Keine zweite unabhängige Quelle gefunden"}. Danach holt get_editorial_brief den nächsten Weg.',
            Schema::object([
                'id' => Schema::integer('Nummer des Auftrags'),
                'status' => Schema::choice(self::RELEASE_STATUSES, 'open = wieder freigeben, failed = abbrechen'),
                'note' => Schema::text('Begründung, bei failed Pflicht, höchstens 2000 Zeichen'),
            ], ['id', 'status']),
            Tool::annotations('Auftrag zurückgeben', 'WD'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($queue): ToolResult {
                $item = $queue->release((int) $args['id'], (string) $args['status'], isset($args['note']) ? (string) $args['note'] : null, $actor);

                return ToolResult::json(['item' => $item]);
            }
        ));
    }

    /** @return array<string, mixed> */
    private static function attachmentSchema(): array
    {
        return Schema::object([
            'url' => Schema::text('Bild-Adresse, wird serverseitig geladen'),
            'data_base64' => Schema::text('Dateiinhalt in Base64'),
            'filename' => Schema::text('Dateiname zu data_base64'),
            'uid' => Schema::text('UID eines vorhandenen Mediums'),
            'own_material' => Schema::boolean('Eigenes Material des Inhabers: Urheber und Lizenz werden gesetzt'),
            'note' => Schema::text('Hinweis zum Anhang, höchstens 500 Zeichen'),
            'alt' => Schema::text('Alt-Text'),
            'caption' => Schema::text('Bildunterschrift'),
            'credit' => Schema::text('Urheber'),
            'license' => Schema::text('Lizenz'),
            'license_url' => Schema::text('Lizenz-Link'),
            'source_url' => Schema::text('Herkunftsseite'),
        ]);
    }

    /** @param array<string, mixed> $args */
    private static function limit(array $args): int
    {
        return min(max((int) ($args['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
    }

    /**
     * Prüft alle Anhänge, bevor das erste Medium entsteht, und verwandelt sie dann der Reihe nach in Medien.
     *
     * @param mixed $attachments
     * @return list<array{uid: string, note: string|null, own_material: bool}>
     */
    private static function resolveAttachments(mixed $attachments, MediaService $media, Actor $actor): array
    {
        if (!is_array($attachments) || !array_is_list($attachments) || count($attachments) > self::ATTACHMENTS_MAX) {
            throw self::invalidAttachment(null, sprintf('muss eine Liste mit höchstens %d Anhängen sein', self::ATTACHMENTS_MAX));
        }

        $decoded = [];
        foreach ($attachments as $index => $attachment) {
            $decoded[$index] = self::checkAttachment($index, $attachment);
        }

        $result = [];
        foreach ($attachments as $index => $attachment) {
            $uid = self::mediumUid($attachment, $decoded[$index], $media, $actor);
            $result[] = [
                'uid' => $uid,
                'note' => $attachment['note'] ?? null,
                'own_material' => $attachment['own_material'] ?? false,
            ];
        }

        return $result;
    }

    /** @return string|null die dekodierten Bytes bei data_base64, sonst null */
    private static function checkAttachment(int $index, mixed $attachment): ?string
    {
        if (!is_array($attachment) || ($attachment !== [] && array_is_list($attachment))) {
            throw self::invalidAttachment($index, 'muss ein Objekt sein, z. B. {"url": "https://…"}');
        }
        $given = array_values(array_filter(self::ATTACHMENT_SOURCES, static fn (string $key): bool => isset($attachment[$key])));
        if (count($given) !== 1) {
            throw self::invalidAttachment($index, 'braucht genau eines von url, data_base64 (mit filename) oder uid');
        }
        foreach (['url', 'data_base64', 'uid', 'filename', 'note', ...self::MEDIA_META_FIELDS] as $textField) {
            if (isset($attachment[$textField]) && !is_string($attachment[$textField])) {
                throw self::invalidAttachment($index, sprintf('hat „%s“ nicht als Text', $textField));
            }
        }
        if (isset($attachment['own_material']) && !is_bool($attachment['own_material'])) {
            throw self::invalidAttachment($index, 'hat own_material nicht als true oder false');
        }
        if ($given[0] !== 'data_base64') {
            return null;
        }
        if (!isset($attachment['filename']) || trim($attachment['filename']) === '') {
            throw self::invalidAttachment($index, 'braucht zu data_base64 einen filename, z. B. "diagramm.png"');
        }
        $bytes = base64_decode($attachment['data_base64'], true);
        if ($bytes === false || $bytes === '') {
            throw self::invalidAttachment($index, 'enthält kein gültiges Base64');
        }

        return $bytes;
    }

    /**
     * @param array<string, mixed> $attachment
     * @param string|null $bytes dekodierter Inhalt bei data_base64
     */
    private static function mediumUid(array $attachment, ?string $bytes, MediaService $media, Actor $actor): string
    {
        if (isset($attachment['uid'])) {
            return $attachment['uid'];
        }

        $meta = array_intersect_key($attachment, array_flip(self::MEDIA_META_FIELDS));
        if ($bytes !== null) {
            return (string) $media->store($bytes, $meta, 'upload', $actor)['uid'];
        }

        $meta['source_url'] ??= $attachment['url'];

        return (string) $media->importFromUrl($attachment['url'], $meta, $actor)['uid'];
    }

    private static function invalidAttachment(?int $index, string $reason): DomainError
    {
        $subject = $index === null ? 'Das Feld „attachments“' : sprintf('Anhang %d', $index + 1);

        return new DomainError('invalid_value', sprintf('%s %s.', $subject, $reason), ['field' => 'attachments']);
    }
}
