<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaService;
use Dispatch\Mcp\ImageBlocks;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class MediaTools
{
    private const KINDS = ['image', 'video', 'audio', 'document'];
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;
    private const META_FIELDS = ['alt', 'caption', 'credit', 'license', 'license_url', 'source_url'];
    private const NON_IMAGE_NOTE = 'Signierte Links folgen mit Meilenstein 4';

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var MediaService $media */
        $media = $services['media'];

        self::registerWriting($registry, $media);
        self::registerReading($registry, $media);
    }

    private static function registerWriting(Registry $registry, MediaService $media): void
    {
        $registry->add(new Tool(
            'import_media_from_url',
            'Bild aus dem Web importieren',
            'Lädt eine Datei (Bild, Video, Audio, PDF) von einer öffentlichen http/https-Adresse und legt sie als Medium an; Adressen im lokalen Netz werden abgewiesen. Für fremde Bilder Urheber, Lizenz und Lizenz-Link angeben; source_url ist standardmäßig die Adresse selbst, besser die Seite, auf der das Bild steht. Beispiel: {"url": "https://upload.wikimedia.org/…/Bild.jpg", "alt": "Rastertunnelmikroskop", "credit": "Max Muster", "license": "CC BY-SA 4.0", "license_url": "https://creativecommons.org/licenses/by-sa/4.0/", "source_url": "https://commons.wikimedia.org/wiki/File:Bild.jpg"}. Die Antwort enthält die uid für create_post (cover_media_uid) und duplicate (true, wenn das Bild schon vorhanden war).',
            Schema::object(['url' => Schema::text('Adresse der Datei')] + self::metaProperties(), ['url']),
            Tool::annotations('Bild aus dem Web importieren', 'WO'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($media): ToolResult {
                $meta = self::meta($args);
                $meta['source_url'] ??= $args['url'];

                return ToolResult::json(['media' => $media->importFromUrl((string) $args['url'], $meta, $actor)]);
            }
        ));

        $registry->add(new Tool(
            'upload_media',
            'Datei hochladen',
            'Legt eine Datei aus Base64-Daten als Medium an (JPEG, PNG, WebP, GIF, MP4, WebM, MP3, M4A, OGG, PDF; kein SVG). Gedacht für Material, das nicht im Netz liegt; bei Webbildern nimm import_media_from_url. Beispiel: {"data_base64": "iVBORw0KGgo…", "filename": "diagramm.png", "alt": "Aufbau des Experiments"}. Die Antwort enthält die uid.',
            Schema::object([
                'data_base64' => Schema::text('Dateiinhalt in Base64'),
                'filename' => Schema::text('Dateiname, z. B. "diagramm.png"'),
            ] + self::metaProperties(), ['data_base64', 'filename']),
            Tool::annotations('Datei hochladen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($media): ToolResult {
                $bytes = base64_decode((string) $args['data_base64'], true);
                if ($bytes === false || $bytes === '') {
                    throw new DomainError('invalid_argument', 'Das Feld „data_base64“ enthält kein gültiges Base64. Beispiel: {"data_base64": "iVBORw0KGgo…", "filename": "bild.png"}.', ['field' => 'data_base64']);
                }

                return ToolResult::json(['media' => $media->store($bytes, self::meta($args), 'upload', $actor)]);
            }
        ));

        $registry->add(new Tool(
            'update_media',
            'Medien-Angaben ändern',
            'Ändert Alt-Text, Bildunterschrift, Urheber, Lizenz und Quelle eines Mediums; nur die genannten Felder. Ist das Medium schon öffentlich, entsteht ein Änderungsvorschlag (change_id) statt einer direkten Änderung. Beispiel: {"uid": "m1a2b3c4d5", "alt": "Neuer Alt-Text"}.',
            Schema::object(['uid' => Schema::text('UID des Mediums')] + self::metaProperties(), ['uid']),
            Tool::annotations('Medien-Angaben ändern', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($media): ToolResult {
                $result = $media->updateMeta((string) $args['uid'], self::meta($args), $actor);

                return ToolResult::json($result + ['media' => $media->get((string) $args['uid'])]);
            }
        ));
    }

    private static function registerReading(Registry $registry, MediaService $media): void
    {
        $registry->add(new Tool(
            'list_media',
            'Medien auflisten',
            'Listet Medien, neueste zuerst, filterbar nach Art und Suchtext (Alt-Text, Bildunterschrift, Urheber). Nützlich, um vorhandene Bilder wiederzuverwenden, statt sie neu zu importieren. Beispiel: {"kind": "image", "query": "Quanten", "limit": 10}. Ein einzelnes Medium samt Bild liefert get_media.',
            Schema::object([
                'kind' => Schema::choice(self::KINDS, 'Art des Mediums'),
                'query' => Schema::text('Suchtext'),
                'limit' => Schema::integer('Höchstens so viele Medien, 1 bis 100; Standard 50'),
                'offset' => Schema::integer('Überspringt so viele Medien; Standard 0'),
            ]),
            Tool::annotations('Medien auflisten', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($media): ToolResult {
                $limit = min(max((int) ($args['limit'] ?? self::DEFAULT_LIMIT), 1), self::MAX_LIMIT);
                $items = $media->list(
                    isset($args['kind']) ? (string) $args['kind'] : null,
                    $limit,
                    max(0, (int) ($args['offset'] ?? 0)),
                    isset($args['query']) ? (string) $args['query'] : null
                );

                return ToolResult::json(['items' => $items]);
            }
        ));

        $registry->add(new Tool(
            'get_media',
            'Medium ansehen',
            'Liefert die Angaben eines Mediums und, bei Bildern mit view: true (Standard), das Bild selbst, damit du es beurteilen kannst (Kante höchstens 1024 px). Mit view: false kommen nur die Angaben. Videos, Audio und PDFs liefern nur die Angaben. Beispiel: {"uid": "m1a2b3c4d5"}.',
            Schema::object([
                'uid' => Schema::text('UID des Mediums'),
                'view' => Schema::boolean('Bild mitliefern; Standard true'),
            ], ['uid']),
            Tool::annotations('Medium ansehen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($media): ToolResult {
                $record = $media->get((string) $args['uid']);
                if ($record['kind'] !== 'image') {
                    return ToolResult::json(['media' => $record, 'note' => self::NON_IMAGE_NOTE]);
                }
                if (!(bool) ($args['view'] ?? true)) {
                    return ToolResult::json(['media' => $record]);
                }

                $images = ImageBlocks::forMedia($media, [$record]);
                $data = ['media' => $record];
                if ($images['blocks'] === []) {
                    $data['note'] = 'Für dieses Bild gibt es keine darstellbare Fassung bis 1024 px.';
                }

                return ToolResult::json($data, $images['blocks']);
            }
        ));
    }

    /** @return array<string, array<string, string>> */
    private static function metaProperties(): array
    {
        return [
            'alt' => Schema::text('Alt-Text für Screenreader, beschreibt das Bild'),
            'caption' => Schema::text('Bildunterschrift'),
            'credit' => Schema::text('Urheber'),
            'license' => Schema::text('Lizenz, z. B. "CC BY-SA 4.0"'),
            'license_url' => Schema::text('Link auf den Lizenztext'),
            'source_url' => Schema::text('Seite, auf der das Bild steht'),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function meta(array $args): array
    {
        return array_intersect_key($args, array_flip(self::META_FIELDS));
    }
}
