<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Domain\DomainError;
use Dispatch\Util\Log;
use Dispatch\Util\Markdown;
use PDO;
use RuntimeException;
use Throwable;

use function Dispatch\Site\seitenUrl;

/**
 * Erzeugt die statische Website in `web_root` (ADR-014). Die Wurzel teilen sich CardMaker und
 * CamScanner: geschrieben und gelöscht wird nur, was `assertWritable` durchlässt.
 */
final class Builder
{
    private const LOCK_NAME = 'dispatch_build';
    private const LOCK_TIMEOUT = 30;
    private const RELATED_POSTS = 3;
    private const NOT_FOUND_POSTS = 3;
    private const COMPANION_TAGS = 8;

    /** Dokumentadressen, die der Builder schreibt (`builder_top_level`), mit ihrem `robots`-Wert. */
    private const DOCUMENT_ROBOTS = [
        'ueber' => 'index, follow',
        'impressum' => 'noindex, follow',
        'datenschutz' => 'noindex, follow',
    ];

    private SiteData $site;

    /** @var array<string, string> Name → URL der Theme-Dateien, vom laufenden Bau gesetzt */
    private array $assetUrls = [];

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly PDO $pdo,
        private readonly int $lockTimeout = self::LOCK_TIMEOUT,
    ) {
    }

    /** @return array{written: int, unchanged: int, deleted: int, ms: int} */
    public function build(): array
    {
        $startedAt = hrtime(true);
        $this->acquireLock();

        try {
            $this->site = SiteData::load($this->pdo, $this->config);
            $assets = Assets::collect($this->config);
            $this->assetUrls = $assets['urls'];

            $files = $this->collect($assets['files']);
            $copies = $this->collectMediaCopies();
            $newPaths = array_map('strval', array_keys($files + $copies));

            $manifest = new Manifest((string) $this->config['storage_dir'] . '/manifest.json');
            $oldPaths = $manifest->read();
            // Alt ∪ neu zuerst: bricht der Lauf ab, kennt das Manifest jede Datei, die er angelegt haben kann.
            $manifest->write(array_values(array_unique(array_merge($oldPaths, $newPaths))));

            $written = 0;
            $unchanged = 0;
            foreach ($files as $path => $content) {
                if ($this->writeFile((string) $path, $content)) {
                    $written++;
                } else {
                    $unchanged++;
                }
            }
            foreach ($copies as $target => $source) {
                if ($this->copyMedia((string) $target, $source)) {
                    $written++;
                } else {
                    $unchanged++;
                }
            }

            $deleted = $this->deleteStale($oldPaths, $newPaths);
            $manifest->write($newPaths);
        } finally {
            $this->releaseLock();
        }

        return [
            'written' => $written,
            'unchanged' => $unchanged,
            'deleted' => $deleted,
            'ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /**
     * Alle Seiten im Speicher: Pfad → Inhalt. Spätere Seitentypen kommen als eigene `collect<Seitentyp>()` dazu.
     *
     * @param array<string, string> $assetFiles
     * @return array<string, string>
     */
    private function collect(array $assetFiles): array
    {
        return $assetFiles
            + $this->collectHome()
            + $this->collectArticles()
            + $this->collectCategories()
            + $this->collectTags()
            + $this->collectSearch()
            + $this->collectDocuments()
            + $this->collectNotFound();
    }

    /** Startseite: Seite 1 mit Aufmacher und `posts_per_page` Karten, danach `seite/{n}/`. @return array<string, string> */
    private function collectHome(): array
    {
        $posts = $this->site->posts;
        $feature = $posts[0] ?? null;
        $pages = $this->paginate(array_slice($posts, 1));
        $total = count($pages);
        $siteTitle = (string) $this->site->settings['site_title'];

        $files = [];
        foreach ($pages as $index => $cards) {
            $number = $index + 1;
            $url = seitenUrl('/', $number);
            $title = $siteTitle;
            if ($number > 1) {
                $title = 'Ältere Artikel – Seite ' . $number . ' · ' . $siteTitle;
            }

            $files[$this->pageFile($url)] = $this->page('home', [
                'aufmacher' => $number === 1 ? $feature : null,
                'posts' => $cards,
                'seite' => $number,
                'gesamt' => $total,
            ], [
                'title' => $title,
                'description' => (string) $this->site->settings['site_description'],
                'canonical' => $url,
                'robots' => 'index, follow',
                'bodyPage' => 'start',
                'nameAlsH1' => $number === 1,
            ]);
        }

        return $files;
    }

    /** @return array<string, string> */
    private function collectArticles(): array
    {
        $renderer = new ContentRenderer();
        $categories = $this->categoriesBySlug();

        $files = [];
        foreach ($this->site->posts as $position => $post) {
            $rendered = $renderer->render($post, $this->site);
            $rendered['html'] = $this->applyLayoutDefaults($rendered['html'], (string) $post['layout']);

            $primary = $post['primary_category'];
            $activeCategory = '';
            if ($primary !== null) {
                $activeCategory = (string) ($primary['parent_slug'] ?? $primary['slug']);
            }

            $description = trim((string) $post['seo_description']);
            if ($description === '') {
                $description = trim((string) $post['excerpt']);
            }
            $title = trim((string) $post['seo_title']);
            if ($title === '') {
                $title = $post['title'] . ' · ' . $this->site->settings['site_title'];
            }

            $files[$this->pageFile($post['url'])] = $this->page('article', [
                'post' => $post,
                'rendered' => $rendered,
                'krumen' => $this->breadcrumbs($primary, $categories),
                'related' => $this->relatedPosts($post),
                'older' => $this->site->posts[$position + 1] ?? null,
                'newer' => $position > 0 ? $this->site->posts[$position - 1] : null,
            ], [
                'title' => $title,
                'description' => $description,
                'canonical' => $post['url'],
                'robots' => 'index, follow',
                'bodyPage' => 'artikel',
                'activeCategory' => $activeCategory,
                'ogType' => 'article',
                'ogImage' => $post['cover'],
                'scripts' => [$this->assetUrls['artikel']],
            ]);
        }

        return $files;
    }

    /** Einzelseiten aller Kategorien (mit Seitenzahlen) und die Übersicht. @return array<string, string> */
    private function collectCategories(): array
    {
        $categories = $this->categoriesBySlug();
        $siteTitle = (string) $this->site->settings['site_title'];

        $files = [];
        foreach ($this->site->categories as $category) {
            $pages = $this->paginate($this->postsInCategory($category));
            $krumen = $this->breadcrumbs($category, $categories);
            foreach ($pages as $index => $cards) {
                $number = $index + 1;
                $url = seitenUrl($category['url'], $number);
                $files[$this->pageFile($url)] = $this->page('category', [
                    'category' => $category,
                    'krumen' => $krumen,
                    'posts' => $cards,
                    'seite' => $number,
                    'gesamt' => count($pages),
                ], [
                    'title' => $category['name'] . ' · ' . $siteTitle,
                    'description' => (string) $category['description'],
                    'canonical' => $url,
                    'robots' => 'index, follow',
                    'bodyPage' => 'kategorie',
                    'activeCategory' => (string) ($category['parent_slug'] ?? $category['slug']),
                ]);
            }
        }

        return $files + $this->collectCategoryOverview();
    }

    /** @return array<string, string> */
    private function collectCategoryOverview(): array
    {
        $mainCategories = [];
        foreach ($this->site->categories as $category) {
            if ($category['parent_id'] === null) {
                $mainCategories[] = $category;
            }
        }

        $postsById = [];
        foreach ($this->site->posts as $post) {
            $postsById[$post['id']] = $post;
        }

        $html = $this->page('categories', [
            'mainCategories' => $mainCategories,
            'postsById' => $postsById,
            'subCategoryCount' => count($this->site->categories) - count($mainCategories),
            'postCount' => count($this->site->posts),
        ], [
            'title' => 'Alle Kategorien · ' . $this->site->settings['site_title'],
            'description' => 'Alle Ressorts und Unterkategorien im Überblick.',
            'canonical' => '/kategorien/',
            'robots' => 'index, follow',
            'bodyPage' => 'kategorien',
        ]);

        return ['kategorien/index.html' => $html];
    }

    /** @return array<string, string> */
    private function collectTags(): array
    {
        $files = [];
        foreach ($this->site->tags as $tag) {
            // Ein Name ohne verwertbare Zeichen ergäbe `tag//` — den gibt es als Adresse nicht.
            if ($tag['slug'] === '') {
                continue;
            }

            $tagged = $this->postsWithTag($tag['slug']);
            $pages = $this->paginate($tagged);
            foreach ($pages as $index => $cards) {
                $number = $index + 1;
                $url = seitenUrl($tag['url'], $number);
                $files[$this->pageFile($url)] = $this->page('tag', [
                    'tag' => $tag,
                    'companions' => $this->companionTags($tag['slug'], $tagged),
                    'posts' => $cards,
                    'total' => count($tagged),
                    'seite' => $number,
                    'gesamt' => count($pages),
                ], [
                    'title' => '#' . $tag['name'] . ' · ' . $this->site->settings['site_title'],
                    'description' => 'Alle Artikel zum Schlagwort „' . $tag['name'] . '“.',
                    'canonical' => $url,
                    'robots' => 'index, follow',
                    'bodyPage' => 'tag',
                ]);
            }
        }

        return $files;
    }

    /** Statische Hülle: Filter, Treffer und Seitenzahlen füllt `suche.js` im Browser. @return array<string, string> */
    private function collectSearch(): array
    {
        $html = $this->page('search', [], [
            'title' => 'Suche · ' . $this->site->settings['site_title'],
            'canonical' => '/suche/',
            'robots' => 'noindex, follow',
            'bodyPage' => 'suche',
        ]);

        return ['suche/index.html' => $html];
    }

    /** @return array<string, string> */
    private function collectDocuments(): array
    {
        $files = [];
        foreach ($this->site->documents as $publicSlug => $document) {
            $publicSlug = (string) $publicSlug;
            if (!isset(self::DOCUMENT_ROBOTS[$publicSlug])) {
                Log::error('Dokument mit unbekannter Adresse übersprungen', ['public_slug' => $publicSlug]);

                continue;
            }

            $files[$publicSlug . '/index.html'] = $this->page('document', [
                'title' => $document['title'],
                'html' => Markdown::toHtml($document['markdown']),
            ], [
                'title' => $document['title'] . ' · ' . $this->site->settings['site_title'],
                'canonical' => $document['url'],
                'robots' => self::DOCUMENT_ROBOTS[$publicSlug],
                'bodyPage' => 'dokument',
            ]);
        }

        return $files;
    }

    /** @return array<string, string> */
    private function collectNotFound(): array
    {
        $html = $this->page('404', ['posts' => array_slice($this->site->posts, 0, self::NOT_FOUND_POSTS)], [
            'title' => 'Seite nicht gefunden · ' . $this->site->settings['site_title'],
            'robots' => 'noindex',
            'bodyPage' => 'fehler',
        ]);

        return ['404.html' => $html];
    }

    /**
     * Teilt Artikel in Seiten zu `posts_per_page`; ohne Artikel bleibt eine leere Seite 1.
     *
     * @param list<array<string, mixed>> $posts
     * @return list<list<array<string, mixed>>>
     */
    private function paginate(array $posts): array
    {
        $perPage = max(1, (int) $this->site->settings['posts_per_page']);
        $pages = array_chunk($posts, $perPage);
        if ($pages === []) {
            return [[]];
        }

        return $pages;
    }

    /** `/kategorie/a/` → `kategorie/a/index.html`, `/` → `index.html` */
    private function pageFile(string $url): string
    {
        return ltrim($url, '/') . 'index.html';
    }

    /** @return array<string, array<string, mixed>> */
    private function categoriesBySlug(): array
    {
        $bySlug = [];
        foreach ($this->site->categories as $category) {
            $bySlug[$category['slug']] = $category;
        }

        return $bySlug;
    }

    /**
     * Start › (Eltern ›) Kategorie; ohne Kategorie nur der Start.
     *
     * @param array<string, mixed>|null $category
     * @param array<string, array<string, mixed>> $categories
     * @return list<array{name: string, url: string}>
     */
    private function breadcrumbs(?array $category, array $categories): array
    {
        $krumen = [['name' => 'Start', 'url' => '/']];
        if ($category === null) {
            return $krumen;
        }

        $parent = $categories[(string) $category['parent_slug']] ?? null;
        if ($parent !== null) {
            $krumen[] = ['name' => $parent['name'], 'url' => $parent['url']];
        }
        $krumen[] = ['name' => $category['name'], 'url' => $category['url']];

        return $krumen;
    }

    /**
     * Artikel einer Kategorie; die Oberkategorie zählt die Artikel ihrer Unterkategorien mit (wie `post_count`).
     *
     * @param array<string, mixed> $category
     * @return list<array<string, mixed>>
     */
    private function postsInCategory(array $category): array
    {
        return array_values(array_filter(
            $this->site->posts,
            static function (array $post) use ($category): bool {
                foreach ($post['categories'] as $assigned) {
                    if ($assigned['slug'] === $category['slug'] || $assigned['parent_slug'] === $category['slug']) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /** @return list<array<string, mixed>> */
    private function postsWithTag(string $tagSlug): array
    {
        return array_values(array_filter(
            $this->site->posts,
            static fn (array $post): bool => in_array($tagSlug, array_column($post['tags'], 'slug'), true)
        ));
    }

    /**
     * Die Schlagwörter, die in diesen Artikeln am häufigsten mitvorkommen.
     *
     * @param list<array<string, mixed>> $tagged
     * @return list<array{name: string, slug: string, together: int}>
     */
    private function companionTags(string $tagSlug, array $tagged): array
    {
        $companions = [];
        foreach ($tagged as $post) {
            foreach ($post['tags'] as $other) {
                if ($other['slug'] === $tagSlug || $other['slug'] === '') {
                    continue;
                }
                if (!isset($companions[$other['slug']])) {
                    $companions[$other['slug']] = ['name' => $other['name'], 'slug' => $other['slug'], 'together' => 0];
                }
                $companions[$other['slug']]['together']++;
            }
        }

        $list = array_values($companions);
        usort($list, static fn (array $left, array $right): int => [$right['together'], mb_strtolower($left['name'])] <=> [$left['together'], mb_strtolower($right['name'])]);

        return array_slice($list, 0, self::COMPANION_TAGS);
    }

    /**
     * „Mehr dazu“: zuerst gleiche Hauptkategorie, dann die meisten gemeinsamen Schlagwörter, dann die neuesten.
     *
     * @param array<string, mixed> $post
     * @return list<array<string, mixed>>
     */
    private function relatedPosts(array $post): array
    {
        $primarySlug = $post['primary_category']['slug'] ?? null;
        $tagSlugs = array_column($post['tags'], 'slug');

        $candidates = [];
        foreach ($this->site->posts as $position => $other) {
            if ($other['id'] === $post['id']) {
                continue;
            }
            $isSameCategory = $primarySlug !== null && ($other['primary_category']['slug'] ?? null) === $primarySlug;
            $sharedTags = count(array_intersect($tagSlugs, array_column($other['tags'], 'slug')));
            $candidates[] = ['rank' => [$isSameCategory ? 0 : 1, -$sharedTags, $position], 'post' => $other];
        }
        usort($candidates, static fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);

        return array_map(
            static fn (array $candidate): array => $candidate['post'],
            array_slice($candidates, 0, self::RELATED_POSTS)
        );
    }

    /** `longform` bekommt den Initialbuchstaben, wenn der Agent keinen gesetzt hat. */
    private function applyLayoutDefaults(string $html, string $layout): string
    {
        if ($layout !== 'longform' || str_contains($html, 'dropcap')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<p(?: class="([^"]*)")?>/',
            static function (array $match): string {
                $classes = trim(($match[1] ?? '') . ' dropcap');

                return '<p class="' . $classes . '">';
            },
            $html,
            1
        );
    }

    /**
     * Nur Varianten der Medien, die ein Live-Artikel nutzt; das Original eines Bildes bleibt im Speicher.
     *
     * @return array<string, string> Ziel (relativ zur Wurzel) → Quelldatei
     */
    private function collectMediaCopies(): array
    {
        $mediaDir = (string) $this->config['storage_dir'] . '/media';

        $copies = [];
        foreach ($this->site->media as $uid => $medium) {
            $names = $medium['kind'] === 'image' ? $this->variantFiles($medium) : ['original.' . $medium['ext']];
            foreach ($names as $name) {
                $source = $mediaDir . '/' . $uid . '/' . $name;
                // Dateinamen kommen aus der Datenbank: nur reine Namen, nie Pfade.
                if ($name !== basename($name) || !is_file($source)) {
                    Log::error('Mediendatei fehlt oder hat einen ungültigen Namen', ['uid' => $uid, 'file' => $name]);

                    continue;
                }
                $copies['media/' . $uid . '/' . $name] = $source;
            }
        }

        return $copies;
    }

    /**
     * @param array<string, mixed> $medium
     * @return list<string>
     */
    private function variantFiles(array $medium): array
    {
        $names = [];
        foreach ((array) $medium['variants'] as $variant) {
            foreach ((array) $variant['files'] as $name) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Rendert die Seitenvorlage und setzt sie in `layout.php`.
     *
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $meta
     */
    public function page(string $template, array $vars, array $meta): string
    {
        $shared = ['site' => $this->site, 'assets' => $this->assetUrls];
        $appDir = (string) $this->config['app_dir'];

        $content = $this->render($appDir . '/templates/' . $template . '.php', $vars + $shared);

        return $this->render($appDir . '/templates/layout.php', $shared + [
            'content' => $content,
            'meta' => $meta,
            'baseUrl' => rtrim((string) $this->config['base_url'], '/'),
        ]);
    }

    /**
     * Teilvorlage aus `templates/partials/`; Seitenvorlagen rufen sie als `$this->partial(…)`.
     *
     * @param array<string, mixed> $vars
     */
    public function partial(string $name, array $vars): string
    {
        $shared = ['site' => $this->site, 'assets' => $this->assetUrls];

        return $this->render((string) $this->config['app_dir'] . '/templates/partials/' . $name . '.php', $vars + $shared);
    }

    /** @param array<string, mixed> $variables */
    public function render(string $template, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            include $template;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    }

    /** @return bool true, wenn die Datei geschrieben wurde; false, wenn sie schon denselben Inhalt hatte */
    public function writeFile(string $relative, string $content): bool
    {
        $this->assertWritable($relative);

        $target = $this->absolutePath($relative);
        if (is_file($target) && hash_equals((string) sha1_file($target), sha1($content))) {
            return false;
        }

        $directory = $this->prepareDirectory($target, $relative);
        $temporaryFile = $this->temporaryName($directory, $target);
        if (file_put_contents($temporaryFile, $content) === false) {
            throw new RuntimeException('Datei nicht schreibbar: ' . $relative);
        }
        $this->replace($temporaryFile, $target, $relative);

        return true;
    }

    /**
     * Medien ändern sich nie (ADR-011): gleiche Größe heißt gleiche Datei.
     *
     * @return bool true, wenn kopiert wurde
     */
    private function copyMedia(string $relative, string $source): bool
    {
        $this->assertWritable($relative);

        $target = $this->absolutePath($relative);
        if (is_file($target) && filesize($target) === filesize($source)) {
            return false;
        }

        $directory = $this->prepareDirectory($target, $relative);
        $temporaryFile = $this->temporaryName($directory, $target);
        if (!copy($source, $temporaryFile)) {
            throw new RuntimeException('Medium nicht kopierbar: ' . $relative);
        }
        $this->replace($temporaryFile, $target, $relative);

        return true;
    }

    /**
     * Löscht, was das alte Manifest kannte und der neue Bau nicht mehr erzeugt — und sonst nichts.
     *
     * @param list<string> $oldPaths
     * @param list<string> $newPaths
     * @return int Zahl gelöschter Dateien
     */
    private function deleteStale(array $oldPaths, array $newPaths): int
    {
        $deleted = 0;
        foreach (array_diff($oldPaths, $newPaths) as $relative) {
            try {
                $this->assertWritable($relative);
            } catch (RuntimeException $exception) {
                Log::error('Manifest-Eintrag verweigert, nicht gelöscht', ['path' => $relative, 'reason' => $exception->getMessage()]);

                continue;
            }

            $target = $this->absolutePath($relative);
            if (!is_file($target)) {
                continue;
            }
            if (unlink($target)) {
                $deleted++;
                $this->removeEmptyParents($relative);
            }
        }

        return $deleted;
    }

    /** Entfernt leere Elternordner bis unterhalb des ersten Segments; `artikel/` selbst bleibt. */
    private function removeEmptyParents(string $relative): void
    {
        $directory = dirname(str_replace('\\', '/', $relative));
        while (str_contains($directory, '/')) {
            $absolute = $this->absolutePath($directory);
            $entries = is_dir($absolute) ? scandir($absolute) : false;
            if ($entries === false || array_diff($entries, ['.', '..']) !== []) {
                return;
            }
            if (!rmdir($absolute)) {
                return;
            }
            $directory = dirname($directory);
        }
    }

    /**
     * Zweite Schreibsperre neben dem Manifest (ADR-014): nur Pfade unter den eigenen Namen der obersten Ebene.
     *
     * @throws RuntimeException
     */
    private function assertWritable(string $relative): void
    {
        $isAbsolute = str_starts_with($relative, '/')
            || str_starts_with($relative, '\\')
            || preg_match('/^[A-Za-z]:/', $relative) === 1;
        if ($relative === '' || $isAbsolute) {
            throw new RuntimeException('Absoluter oder leerer Pfad: ' . $relative);
        }

        $segments = preg_split('#[/\\\\]#', $relative);
        if ($segments === false || in_array('..', $segments, true)) {
            throw new RuntimeException('Pfad mit ".." verweigert: ' . $relative);
        }

        $reserved = array_map('strtolower', (array) $this->config['reserved_top_level']);
        if (in_array(strtolower($segments[0]), $reserved, true)) {
            throw new RuntimeException('Reservierter Name: ' . $segments[0]);
        }

        // Case-sensitiv: auf Windows-Servern wäre „Card" derselbe Ordner wie „card".
        if (!in_array($segments[0], (array) $this->config['builder_top_level'], true)) {
            throw new RuntimeException('Name nicht in builder_top_level: ' . $segments[0]);
        }
    }

    private function absolutePath(string $relative): string
    {
        return rtrim((string) $this->config['web_root'], '/\\') . '/' . $relative;
    }

    private function prepareDirectory(string $target, string $relative): string
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Zielordner nicht anlegbar: ' . $relative);
        }

        return $directory;
    }

    /** Temporäre Datei im Zielordner, damit rename auf demselben Dateisystem atomar bleibt. */
    private function temporaryName(string $directory, string $target): string
    {
        return $directory . '/.' . basename($target) . '.' . bin2hex(random_bytes(4)) . '.tmp';
    }

    private function replace(string $temporaryFile, string $target, string $relative): void
    {
        if (!rename($temporaryFile, $target)) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
            throw new RuntimeException('Datei nicht ersetzbar: ' . $relative);
        }
    }

    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
        $statement->execute([self::LOCK_NAME, $this->lockTimeout]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new DomainError('build_busy', 'Ein anderer Build läuft gerade. Versuche es in einer Minute erneut.');
        }
    }

    private function releaseLock(): void
    {
        $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([self::LOCK_NAME]);
    }
}
