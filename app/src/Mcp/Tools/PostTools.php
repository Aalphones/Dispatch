<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\PreviewLink;
use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\PostService;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class PostTools
{
    private const STATUSES = ['draft', 'review', 'published', 'archived'];
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 100;
    private const SEARCH_DEFAULT_LIMIT = 20;
    private const POST_FIELDS = [
        'title', 'subtitle', 'kicker', 'excerpt', 'slug', 'content_html', 'tags', 'layout', 'accent',
        'cover_media_uid', 'seo_title', 'seo_description', 'origin', 'depth', 'editor_notes', 'ai_assisted',
        'categories', 'sources',
    ];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var PostService $posts */
        $posts = $services['posts'];

        self::registerReading($registry, $posts);
        self::registerWriting($registry, $posts);
        self::registerTransitions($registry, $posts);
        self::registerRevisions($registry, $posts);
    }

    private static function registerReading(Registry $registry, PostService $posts): void
    {
        $registry->add(new Tool(
            'list_posts',
            'Artikel auflisten',
            'Listet Artikel, neueste zuerst, filterbar nach Status, Kategorie, Schlagwort und Jahr. Nützlich für den Überblick (zum Beispiel alle zurückgegebenen Entwürfe) und gegen Dubletten. Beispiel: {"status": "draft", "category": "technik", "limit": 10}. Den ganzen Artikel liefert get_post.',
            Schema::object([
                'status' => Schema::choice(self::STATUSES, 'Nur Artikel in diesem Status'),
                'category' => Schema::text('Kategorie-Slug'),
                'tag' => Schema::text('Schlagwort'),
                'year' => Schema::integer('Veröffentlichungsjahr'),
                'limit' => Schema::integer('Höchstens so viele, 1 bis 100; Standard 50'),
                'offset' => Schema::integer('Überspringt so viele; Standard 0'),
            ]),
            Tool::annotations('Artikel auflisten', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                $entries = $posts->list(
                    isset($args['status']) ? [(string) $args['status']] : [],
                    self::limit($args, self::DEFAULT_LIMIT),
                    max(0, (int) ($args['offset'] ?? 0)),
                    isset($args['category']) ? (string) $args['category'] : null,
                    isset($args['tag']) ? (string) $args['tag'] : null,
                    isset($args['year']) ? (int) $args['year'] : null
                );

                return ToolResult::json(['posts' => $entries]);
            }
        ));

        $registry->add(new Tool(
            'search_posts',
            'Artikel suchen',
            'Volltextsuche in Titel, Untertitel, Auszug und Text; Titeltreffer zuerst. Gedacht für den Dubletten-Check vor dem Schreiben und für interne Verweise. Beispiel: {"query": "Quantencomputer", "limit": 5}. Einen Treffer liest get_post.',
            Schema::object([
                'query' => Schema::text('Suchbegriff, mindestens 2 Zeichen'),
                'limit' => Schema::integer('Höchstens so viele Treffer, 1 bis 50; Standard 20'),
            ], ['query']),
            Tool::annotations('Artikel suchen', 'R'),
            Scopes::READ,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json([
                'posts' => $posts->search((string) $args['query'], self::limit($args, self::SEARCH_DEFAULT_LIMIT)),
            ])
        ));

        $registry->add(new Tool(
            'get_post',
            'Artikel lesen',
            'Liefert einen Artikel mit Quellen, Kategorien, Status und review_note (der Anmerkung des Inhabers bei zurückgegebenen Entwürfen). Das HTML kommt nur mit include_html: true, sonst fehlt content_html, damit die Antwort klein bleibt. Beispiel: {"id": 12, "include_html": true}. Geändert wird mit update_post.',
            Schema::object([
                'id' => Schema::integer('Artikel-ID'),
                'slug' => Schema::text('Alternativ der Slug'),
                'include_html' => Schema::boolean('content_html mitliefern; Standard false'),
            ]),
            Tool::annotations('Artikel lesen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                if (isset($args['id'])) {
                    $post = $posts->get((int) $args['id']);
                } elseif (isset($args['slug'])) {
                    $post = $posts->getBySlug((string) $args['slug']);
                } else {
                    throw new DomainError('invalid_value', 'Gib id oder slug an, z. B. {"id": 12}.', ['field' => 'id']);
                }

                return ToolResult::json(self::present($post, (bool) ($args['include_html'] ?? false)));
            }
        ));
    }

    private static function registerWriting(Registry $registry, PostService $posts): void
    {
        $registry->add(new Tool(
            'create_post',
            'Entwurf anlegen',
            'Legt einen Artikel als Entwurf an. Pflicht sind title und content_html; für die Einreichung verlangt die Formprüfung außerdem Teaser (excerpt), Quellen, Belege im Text, Kategorie und Titelbild (Details: get_style_guide). Mit queue_item_id wird der reservierte Auftrag verknüpft. Beispiel: {"title": "…", "excerpt": "…", "content_html": "<p>…</p>", "categories": ["technik"], "sources": [{"ref": "s1", "kind": "study", "title": "…", "url": "https://…"}]}. Die Antwort enthält sanitizer_notes (was entfernt wurde) und form_check; danach validate_post oder update_post.',
            Schema::object(self::postProperties(true), ['title', 'content_html']),
            Tool::annotations('Entwurf anlegen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                $input = self::postInput($args);
                if (array_key_exists('queue_item_id', $args)) {
                    $input['queue_item_id'] = $args['queue_item_id'];
                }
                $result = $posts->create($input, $actor);

                return ToolResult::json(self::saved($posts, $result));
            }
        ));

        $registry->add(new Tool(
            'update_post',
            'Artikel ändern',
            'Ändert einzelne Felder eines Artikels und legt eine neue Revision an; categories und sources ersetzen die Liste vollständig. Ein eingereichter Artikel wird dadurch wieder Entwurf; bei einem veröffentlichten bleibt die Live-Fassung bis zur Freigabe unverändert. Beispiel: {"id": 12, "excerpt": "Neuer Teaser …"}. Danach validate_post und submit_post.',
            Schema::object(['id' => Schema::integer('Artikel-ID')] + self::postProperties(false), ['id']),
            Tool::annotations('Artikel ändern', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                $result = $posts->update((int) $args['id'], self::postInput($args), $actor);

                return ToolResult::json(self::saved($posts, $result));
            }
        ));

        $registry->add(new Tool(
            'validate_post',
            'Artikel prüfen',
            'Führt die Formprüfung aus, ohne etwas zu ändern: Fehler blockieren das Einreichen, Warnungen nicht; dazu Kennzahlen (Wörter, Quellen, Belege, Bilder, Mindestwerte). Jeder Eintrag nennt Regelcode, Feld und Meldung. Beispiel: {"id": 12}. Bei Fehlern folgt update_post, sonst submit_post.',
            Schema::object(['id' => Schema::integer('Artikel-ID')], ['id']),
            Tool::annotations('Artikel prüfen', 'R'),
            Scopes::READ,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json($posts->validate((int) $args['id'])->toArray())
        ));
    }

    private static function registerTransitions(Registry $registry, PostService $posts): void
    {
        $registry->add(new Tool(
            'submit_post',
            'Artikel einreichen',
            'Prüft den Artikel (wie validate_post) und legt ihn dem Inhaber zur Freigabe vor; Status wird review, öffentlich ändert sich nichts. Bei Formfehlern kommt die Fehlerliste statt der Einreichung. Das Ergebnis enthält preview_url: den Vorschau-Link, den der Inhaber ohne Anmeldung nur lesend öffnen kann (sieben Tage gültig). Beispiel: {"id": 12}. Danach ist der Lauf mit dem Abschlussbericht beendet; zurückziehen geht mit withdraw_post.',
            Schema::object(['id' => Schema::integer('Artikel-ID')], ['id']),
            Tool::annotations('Artikel einreichen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                $post = self::present($posts->submit((int) $args['id'], $actor), false);

                return ToolResult::json($post + ['preview_url' => PreviewLink::url((int) $post['id'])]);
            }
        ));

        $registry->add(new Tool(
            'withdraw_post',
            'Einreichung zurückziehen',
            'Nimmt einen eingereichten Artikel aus der Prüfung zurück, damit du weiter daran arbeiten kannst: ein nie veröffentlichter wird wieder Entwurf, ein veröffentlichter bleibt mit seiner Live-Fassung online. Beispiel: {"id": 12}. Danach update_post und erneut submit_post.',
            Schema::object(['id' => Schema::integer('Artikel-ID')], ['id']),
            Tool::annotations('Einreichung zurückziehen', 'W'),
            Scopes::WRITE,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json(self::present($posts->withdraw((int) $args['id'], $actor), false))
        ));

        $registry->add(new Tool(
            'delete_post',
            'Entwurf löschen',
            'Löscht einen nie veröffentlichten Entwurf samt Revisionen endgültig; ein verknüpfter Queue-Auftrag wird wieder frei. Eingereichtes zuerst mit withdraw_post zurückziehen, Veröffentlichtes lässt sich nicht löschen. Beispiel: {"id": 12, "confirm": true}; ohne confirm: true passiert nichts.',
            Schema::object([
                'id' => Schema::integer('Artikel-ID'),
                'confirm' => Schema::boolean('Muss true sein, sonst wird nichts gelöscht'),
            ], ['id', 'confirm']),
            Tool::annotations('Entwurf löschen', 'WD'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                if ($args['confirm'] !== true) {
                    throw new DomainError('confirm_required', 'Löschen ist endgültig. Rufe das Tool mit "confirm": true erneut auf, wenn du den Entwurf wirklich löschen willst.', ['id' => $args['id']]);
                }
                $posts->discard((int) $args['id'], $actor);

                return ToolResult::json(['deleted' => true, 'id' => (int) $args['id']]);
            }
        ));
    }

    private static function registerRevisions(Registry $registry, PostService $posts): void
    {
        $registry->add(new Tool(
            'list_post_revisions',
            'Revisionen auflisten',
            'Listet die Revisionen eines Artikels mit Nummer, Bearbeiter und Zeitpunkt. Nutze es, um eine frühere Fassung zu finden. Beispiel: {"id": 12}. Wiederhergestellt wird mit restore_post_revision.',
            Schema::object(['id' => Schema::integer('Artikel-ID')], ['id']),
            Tool::annotations('Revisionen auflisten', 'R'),
            Scopes::READ,
            static fn (array $args, Actor $actor): ToolResult => ToolResult::json(['revisions' => $posts->revisions((int) $args['id'])])
        ));

        $registry->add(new Tool(
            'restore_post_revision',
            'Revision wiederherstellen',
            'Stellt den Inhalt einer früheren Revision als neue Revision wieder her (Slug und Herkunft bleiben). Beispiel: {"id": 12, "revision": 2}. Danach validate_post, denn der alte Stand erfüllt die Formvorgaben nicht zwingend.',
            Schema::object([
                'id' => Schema::integer('Artikel-ID'),
                'revision' => Schema::integer('Nummer der Revision aus list_post_revisions'),
            ], ['id', 'revision']),
            Tool::annotations('Revision wiederherstellen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($posts): ToolResult {
                $result = $posts->restoreRevision((int) $args['id'], (int) $args['revision'], $actor);

                return ToolResult::json(self::saved($posts, $result));
            }
        ));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function postProperties(bool $withQueueItem): array
    {
        $properties = [
            'title' => Schema::text('Titel, 1 bis 200 Zeichen, bis etwa 70 ohne Clickbait'),
            'excerpt' => Schema::text('Teaser, 70 bis 180 Zeichen, höchstens 400'),
            'content_html' => Schema::text('Artikeltext als HTML nach get_style_guide; unerlaubtes Markup entfernt der Server und meldet es in sanitizer_notes'),
            'categories' => Schema::list(['type' => 'string'], 'Kategorie-Slugs, die erste ist die Hauptkategorie, 1 bis 3'),
            'subtitle' => Schema::text('Unterzeile, höchstens 300 Zeichen'),
            'kicker' => Schema::text('Dachzeile, höchstens 80 Zeichen'),
            'tags' => Schema::list(['type' => 'string'], 'Schlagwörter, höchstens 10'),
            'layout' => Schema::choice(['standard', 'feature', 'longform', 'briefing'], 'Layout; Standard standard'),
            'accent' => Schema::text('Akzentfarbe als #rrggbb'),
            'cover_media_uid' => Schema::text('UID des Titelbilds (Bild aus der Medienbibliothek)'),
            'sources' => Schema::list(self::sourceSchema(), 'Quellen; ersetzt die Liste vollständig'),
            'seo_title' => Schema::text('Titel für Suchmaschinen, höchstens 200 Zeichen'),
            'seo_description' => Schema::text('Beschreibung für Suchmaschinen, höchstens 300 Zeichen'),
            'slug' => Schema::text('Adresse, Kleinbuchstaben und Bindestriche; ohne Angabe aus dem Titel'),
            'editor_notes' => Schema::text('Rechercheprotokoll: Verworfenes, Offenes'),
            'origin' => Schema::choice(['scheduled', 'manual'], 'Herkunft; bei queue_item_id setzt die Queue sie'),
            'depth' => Schema::choice(['standard', 'deep'], 'Recherchetiefe; bei queue_item_id gilt die des Auftrags'),
            'ai_assisted' => Schema::boolean('KI-Kennzeichnung; Standard true, selbst geschriebene Artikel false'),
        ];
        if ($withQueueItem) {
            $properties['queue_item_id'] = Schema::integer('Nummer des reservierten Auftrags (claim_queue_item)');
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    private static function sourceSchema(): array
    {
        return Schema::object([
            'ref' => Schema::text('Beleg-Schlüssel, z. B. "s1"; im Text als data-cite'),
            'kind' => Schema::choice(['web', 'news', 'study', 'official', 'dataset', 'book', 'interview', 'video', 'podcast', 'social', 'other'], 'Art der Quelle'),
            'title' => Schema::text('Titel der Quelle'),
            'url' => Schema::text('Adresse (bei book, interview, other optional)'),
            'publisher' => Schema::text('Herausgeber'),
            'author' => Schema::text('Autor'),
            'published' => Schema::text('Erscheinungsdatum JJJJ, JJJJ-MM oder JJJJ-MM-TT'),
            'accessed' => Schema::text('Abrufdatum JJJJ-MM-TT'),
            'note' => Schema::text('Hinweis zur Quelle'),
        ], ['ref', 'kind', 'title']);
    }

    /**
     * Übernimmt die Artikel-Felder; Kategorien als Slug-Liste werden zur Form des Services (erste = Hauptkategorie).
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function postInput(array $args): array
    {
        $input = array_intersect_key($args, array_flip(self::POST_FIELDS));
        if (isset($input['categories']) && is_array($input['categories'])) {
            $input['categories'] = array_map(
                static fn (mixed $slug, int $position): mixed => is_string($slug) ? ['slug' => $slug, 'primary' => $position === 0] : $slug,
                $input['categories'],
                array_keys($input['categories'])
            );
        }

        return $input;
    }

    /**
     * @param array{post: array<string, mixed>, removed: list<string>} $result
     * @return array<string, mixed>
     */
    private static function saved(PostService $posts, array $result): array
    {
        $post = $result['post'];

        return [
            'post' => self::present($post, false),
            'sanitizer_notes' => $result['removed'],
            'form_check' => $posts->validate((int) $post['id'])->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private static function present(array $post, bool $withHtml): array
    {
        if (!$withHtml) {
            unset($post['content_html']);
        }

        return $post;
    }

    /** @param array<string, mixed> $args */
    private static function limit(array $args, int $default): int
    {
        return min(max((int) ($args['limit'] ?? $default), 1), self::MAX_LIMIT);
    }
}
