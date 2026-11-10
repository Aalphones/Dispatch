<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\StatsService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class StatsTools
{
    private const GROUPS = ['day', 'post', 'category', 'referrer', 'device', 'outbound', 'search'];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var StatsService $stats */
        $stats = $services['stats'];

        $registry->add(new Tool(
            'get_stats',
            'Statistik lesen',
            'Liefert Zugriffszahlen der Website für einen Zeitraum (Standard: letzte 30 Tage), gruppiert nach Tag, Artikel, Kategorie, Herkunft, Gerät, ausgehenden Links oder Suchbegriffen. Der Filter schränkt auf einen Artikel (post: ID oder Slug) oder einen Pfadanfang (path_prefix) ein. Beispiel: {"from": "2026-10-01", "to": "2026-10-31", "group_by": "post"}. Zahlen zu einem einzelnen Artikel liefert get_post_stats.',
            Schema::object([
                'from' => Schema::text('Beginn, Datum YYYY-MM-DD'),
                'to' => Schema::text('Ende, Datum YYYY-MM-DD'),
                'group_by' => Schema::choice(self::GROUPS, 'Gruppierung; Standard day'),
                'filter' => Schema::object([
                    'post' => ['description' => 'Artikel-ID (Zahl) oder Slug (Text)'],
                    'path_prefix' => Schema::text('Pfadanfang, z. B. "/kategorien/technik"'),
                ]),
            ]),
            Tool::annotations('Statistik lesen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($stats): ToolResult {
                $result = $stats->stats(
                    isset($args['from']) ? (string) $args['from'] : null,
                    isset($args['to']) ? (string) $args['to'] : null,
                    (string) ($args['group_by'] ?? 'day'),
                    (array) ($args['filter'] ?? [])
                );

                return ToolResult::json($result);
            }
        ));

        $registry->add(new Tool(
            'get_post_stats',
            'Artikel-Statistik lesen',
            'Liefert die Zahlen eines Artikels: Summen im Zeitraum (Standard: letzte 30 Tage) sowie Herkunft, Geräte und ausgehende Links. Beispiel: {"slug": "quantenverschraenkung-erklaert", "from": "2026-10-01"}. Den Überblick über alle Artikel liefert get_stats mit group_by "post".',
            Schema::object([
                'id' => Schema::integer('Artikel-ID'),
                'slug' => Schema::text('Alternativ der Slug'),
                'from' => Schema::text('Beginn, Datum YYYY-MM-DD'),
                'to' => Schema::text('Ende, Datum YYYY-MM-DD'),
            ]),
            Tool::annotations('Artikel-Statistik lesen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($stats): ToolResult {
                if (isset($args['id'])) {
                    $idOrSlug = (int) $args['id'];
                } elseif (isset($args['slug'])) {
                    $idOrSlug = (string) $args['slug'];
                } else {
                    throw new DomainError('invalid_value', 'Gib id oder slug an, z. B. {"id": 12}.', ['field' => 'id']);
                }

                return ToolResult::json($stats->postStats(
                    $idOrSlug,
                    isset($args['from']) ? (string) $args['from'] : null,
                    isset($args['to']) ? (string) $args['to'] : null
                ));
            }
        ));
    }
}
