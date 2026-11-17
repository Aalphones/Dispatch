<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\ScheduleService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class ScheduleTools
{
    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var ScheduleService $schedule */
        $schedule = $services['schedule'];

        $registry->add(new Tool(
            'get_publication_schedule',
            'Veröffentlichungsplan lesen',
            'Liefert wartende Artikel mit erwartetem Zeitpunkt und Modus. Nur Einträge mit mode="slot" gehören zur umsortierbaren Warteschlange; feste Termine bleiben unverändert.',
            Schema::object([]),
            Tool::annotations('Veröffentlichungsplan lesen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($schedule): ToolResult {
                $plan = $schedule->plan(new DateTimeImmutable('now', new DateTimeZone('UTC')));

                return ToolResult::json(['items' => $plan['items']]);
            }
        ));

        $registry->add(new Tool(
            'reorder_publication_schedule',
            'Veröffentlichungsplan sortieren',
            'Sortiert die vom Inhaber bereits freigegebenen Queue-Artikel im Veröffentlichungsplan. Übergib die vollständige Liste aller IDs mit mode="slot" aus get_publication_schedule in der gewünschten Reihenfolge. Feste Termine werden ausgelassen. Bei zwischenzeitlichen Änderungen wird nichts gespeichert; dann Plan neu lesen und erneut sortieren. Das Werkzeug gibt nichts frei und veröffentlicht nichts. Beispiel: {"ids": [12, 7, 19]}.',
            Schema::object(['ids' => Schema::list(Schema::integer('Artikelnummer'), 'Vollständige Reihenfolge aller wartenden Queue-Artikel')], ['ids']),
            Tool::annotations('Veröffentlichungsplan sortieren', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($schedule): ToolResult {
                $plan = $schedule->reorder($args['ids'], $actor);

                return ToolResult::json(['items' => $plan['items']]);
            }
        ));
    }
}
