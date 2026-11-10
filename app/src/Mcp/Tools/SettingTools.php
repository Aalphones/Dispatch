<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\SettingService;
use Dispatch\Domain\SiteInfoService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class SettingTools
{
    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var SiteInfoService $siteInfo */
        $siteInfo = $services['site_info'];
        /** @var SettingService $settings */
        $settings = $services['settings'];

        $registry->add(new Tool(
            'get_site_info',
            'Website-Kennzahlen lesen',
            'Liefert die Einstellungen, die Formvorgaben (Mindestzahl Quellen und Wörter), Zahlen zu Artikeln, Queue, Medien und offenen Vorschlägen sowie den Zeitpunkt des letzten Baus. Nützlich, bevor du Einstellungen änderst oder einen Artikel gegen die Formvorgaben planst. Aufruf: {}. Ändern geht mit update_settings.',
            Schema::object(),
            Tool::annotations('Website-Kennzahlen lesen', 'R'),
            Scopes::READ,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json($siteInfo->info())
        ));

        $registry->add(new Tool(
            'update_settings',
            'Einstellungen ändern',
            'Ändert Einstellungen. Interne Werte (posts_per_page, queue_claim_minutes, language, locale) gelten sofort; öffentliche (site_title, site_tagline, site_description, author_name, author_url, accent, ai_disclosure, ai_disclosure_text) werden als Änderungsvorschlag angelegt, den der Inhaber freigibt (Ergebnis: change_id). Formvorgaben wie min_sources lassen sich nicht ändern (not_changeable). Beispiel: {"settings": {"site_title": "Neuer Titel"}}; danach list_changes.',
            Schema::object([
                'settings' => ['type' => 'object', 'description' => 'Einstellung → neuer Wert, z. B. {"site_tagline": "…"}'],
            ], ['settings']),
            Tool::annotations('Einstellungen ändern', 'W'),
            Scopes::ADMIN,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json($settings->set((array) $args['settings'], $actor))
        ));
    }
}
