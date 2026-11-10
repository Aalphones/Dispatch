<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\BriefingService;
use Dispatch\Domain\CategoryService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class CategoryTools
{
    private const FIELD_MAP = [
        'slug' => 'slug',
        'name' => 'name',
        'description' => 'description',
        'position' => 'position',
        'interest' => 'interest',
        'keywords' => 'keywords',
        'avoid' => 'avoid',
        'active' => 'active',
    ];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var CategoryService $categories */
        $categories = $services['categories'];
        /** @var BriefingService $briefing */
        $briefing = $services['briefing'];

        $registry->add(new Tool(
            'list_categories',
            'Kategorien auflisten',
            'Listet die Kategorien mit Slug, Gewicht (interest 0 bis 5), Stichwörtern, Artikelzahl, letztem Artikel und Rotationswert. Brauchst du, um für create_post oder add_to_queue einen gültigen Kategorie-Slug zu wählen. Beispiel: {"include_inactive": true}. Ändern geht mit save_category.',
            Schema::object(['include_inactive' => Schema::boolean('Auch abgeschaltete Kategorien zeigen; Standard false')]),
            Tool::annotations('Kategorien auflisten', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($categories, $briefing): ToolResult {
                $stats = $briefing->categoryStats();
                $slugById = [];
                $entries = $categories->list(true);
                foreach ($entries as $category) {
                    $slugById[$category['id']] = $category['slug'];
                }

                $result = [];
                foreach ($categories->list((bool) ($args['include_inactive'] ?? false)) as $category) {
                    $categoryStats = $stats[$category['id']] ?? ['post_count' => 0, 'last_published_at' => null, 'rotation' => 0];
                    $category['parent'] = $category['parent_id'] === null ? null : ($slugById[$category['parent_id']] ?? null);
                    $result[] = $category + $categoryStats;
                }

                return ToolResult::json(['categories' => $result]);
            }
        ));

        $registry->add(new Tool(
            'save_category',
            'Kategorie speichern',
            'Legt eine Kategorie an (ohne id; slug und name sind dann Pflicht) oder ändert eine (mit id). Gewicht, Stichwörter, Ausschlüsse und active gelten sofort; Name, Beschreibung und andere öffentliche Felder werden als Änderungsvorschlag angelegt (Ergebnis: change_id). Beispiel: {"id": 3, "interest": 4, "keywords": ["quanten"]}. Danach list_categories zur Kontrolle.',
            Schema::object([
                'id' => Schema::integer('ID der Kategorie; fehlt sie, wird eine neue angelegt'),
                'slug' => Schema::text('Adresse, Kleinbuchstaben, Ziffern und Bindestriche, z. B. "kuenstliche-intelligenz"'),
                'name' => Schema::text('Anzeigename, 1 bis 120 Zeichen'),
                'description' => Schema::text('Beschreibung für die Kategorieseite'),
                'parent' => Schema::text('Slug der Oberkategorie; es gibt nur zwei Ebenen'),
                'position' => Schema::integer('Reihenfolge in der Navigation'),
                'interest' => Schema::integer('Gewicht 0 (ausgeschlossen) bis 5'),
                'keywords' => Schema::list(['type' => 'string'], 'Stichwörter für die Themenwahl'),
                'avoid' => Schema::list(['type' => 'string'], 'Themen, die in dieser Kategorie nicht vorkommen sollen'),
                'active' => Schema::boolean('Kategorie aktiv'),
            ]),
            Tool::annotations('Kategorie speichern', 'W'),
            Scopes::ADMIN,
            static function (array $args, Actor $actor) use ($categories): ToolResult {
                $fields = [];
                foreach (self::FIELD_MAP as $argument => $field) {
                    if (array_key_exists($argument, $args)) {
                        $fields[$field] = $args[$argument];
                    }
                }
                if (array_key_exists('parent', $args)) {
                    $fields['parent_id'] = $args['parent'] === null ? null : (int) $categories->get((string) $args['parent'])['id'];
                }

                if (!isset($args['id'])) {
                    return ToolResult::json($categories->create($fields, $actor));
                }

                $id = (int) $args['id'];
                $result = $categories->update($id, $fields, $actor);

                return ToolResult::json($result + ['category' => $categories->get($id)]);
            }
        ));
    }
}
