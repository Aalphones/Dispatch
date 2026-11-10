<?php

declare(strict_types=1);

namespace Dispatch\Site;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Meta-Angaben, OpenGraph und JSON-LD. Reine Funktionen über `SiteData` und die Seitenmeta;
 * `layout.php` gibt das Ergebnis aus, der Feed und die Sitemap nutzen dieselben Bildadressen.
 */
final class Seo
{
    /** Breite des Bildes für Vorschauen und Feed; kleiner als das größte Fallback. */
    public const SHARE_IMAGE_WIDTH = 1200;

    public const THEME_COLOR_LIGHT = '#f6f1e7';
    public const THEME_COLOR_DARK = '#17150f';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

    /**
     * Das JPEG (bzw. PNG) der größten Fallback-Variante bis 1200 px: Vorschaubild, Feed-Anhang, Sitemap.
     *
     * @param array<string, mixed> $media MediaView
     * @return array{path: string, width: int, height: int, mime: string}|null `path` relativ zur Wurzel
     */
    public static function shareImage(array $media): ?array
    {
        $best = null;
        foreach ((array) $media['variants'] as $variant) {
            $width = (int) $variant['w'];
            if ($width > self::SHARE_IMAGE_WIDTH) {
                continue;
            }
            foreach ((array) $variant['files'] as $file) {
                $file = (string) $file;
                if (str_ends_with($file, '.webp')) {
                    continue;
                }
                if ($best === null || $width > $best['width']) {
                    $best = ['file' => $file, 'width' => $width];
                }
            }
        }
        if ($best === null) {
            return null;
        }

        $ratio = (int) $media['width'] > 0 ? (int) $media['height'] / (int) $media['width'] : 1.0;
        $extension = strtolower(pathinfo($best['file'], PATHINFO_EXTENSION));

        return [
            'path' => 'media/' . $media['uid'] . '/' . $best['file'],
            'width' => $best['width'],
            'height' => (int) round($best['width'] * $ratio),
            'mime' => $extension === 'png' ? 'image/png' : 'image/jpeg',
        ];
    }

    /**
     * `<head>`-Zeilen als Liste von Tag-Beschreibungen; `layout.php` maskiert die Werte.
     *
     * @param array<string, mixed> $meta
     * @return list<array{tag: string, attributes: array<string, string>}>
     */
    public static function headTags(array $meta, SiteData $site, string $baseUrl): array
    {
        $tags = [];
        $tags[] = ['tag' => 'meta', 'attributes' => ['name' => 'theme-color', 'content' => self::THEME_COLOR_LIGHT, 'media' => '(prefers-color-scheme: light)']];
        $tags[] = ['tag' => 'meta', 'attributes' => ['name' => 'theme-color', 'content' => self::THEME_COLOR_DARK, 'media' => '(prefers-color-scheme: dark)']];

        if (($meta['ogType'] ?? '') !== 'article') {
            return $tags;
        }

        return array_merge($tags, self::articleTags($meta, $site, $baseUrl));
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{tag: string, attributes: array<string, string>}>
     */
    private static function articleTags(array $meta, SiteData $site, string $baseUrl): array
    {
        $post = $meta['post'];
        $properties = [
            ['og:type', 'article'],
            ['og:site_name', (string) $site->settings['site_title']],
            ['og:locale', 'de_DE'],
            ['og:title', (string) $post['title']],
            ['og:description', (string) ($meta['description'] ?? '')],
            ['og:url', $baseUrl . $post['url']],
        ];

        $image = is_array($meta['ogImage'] ?? null) ? self::shareImage($meta['ogImage']) : null;
        if ($image !== null) {
            $properties[] = ['og:image', $baseUrl . '/' . $image['path']];
            $properties[] = ['og:image:width', (string) $image['width']];
            $properties[] = ['og:image:height', (string) $image['height']];
            $properties[] = ['og:image:alt', (string) $meta['ogImage']['alt']];
        }

        $properties[] = ['article:published_time', self::iso((string) $post['published_at'])];
        $properties[] = ['article:modified_time', self::iso((string) $post['updated_at'])];
        if ($post['primary_category'] !== null) {
            $properties[] = ['article:section', (string) $post['primary_category']['name']];
        }
        foreach ($post['tags'] as $tag) {
            $properties[] = ['article:tag', (string) $tag['name']];
        }

        $tags = [];
        foreach ($properties as [$property, $content]) {
            $tags[] = ['tag' => 'meta', 'attributes' => ['property' => $property, 'content' => $content]];
        }
        $tags[] = ['tag' => 'meta', 'attributes' => ['name' => 'twitter:card', 'content' => 'summary_large_image']];

        return $tags;
    }

    /**
     * JSON-LD-Blöcke einer Seite als fertige JSON-Texte; auch hier der Schutz gegen `</script>` über JSON_HEX_TAG.
     *
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    public static function jsonLd(array $meta, SiteData $site, string $baseUrl): array
    {
        $documents = [];
        if (($meta['bodyPage'] ?? '') === 'start' && ($meta['canonical'] ?? '') === '/') {
            $documents[] = self::webSite($site, $baseUrl);
        }
        if (($meta['ogType'] ?? '') === 'article') {
            $documents[] = self::blogPosting($meta, $site, $baseUrl);
            $documents[] = self::breadcrumbList((array) ($meta['krumen'] ?? []), $baseUrl);
        }

        return array_map(
            static fn (array $document): string => json_encode($document, self::JSON_FLAGS),
            $documents
        );
    }

    /** @return array<string, mixed> */
    private static function webSite(SiteData $site, string $baseUrl): array
    {
        $document = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => (string) $site->settings['site_title'],
            'url' => $baseUrl . '/',
            'inLanguage' => 'de',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $baseUrl . '/suche/?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        $description = trim((string) $site->settings['site_description']);
        if ($description !== '') {
            $document['description'] = $description;
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function blogPosting(array $meta, SiteData $site, string $baseUrl): array
    {
        $post = $meta['post'];
        $document = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $baseUrl . $post['url']],
            'headline' => (string) $post['title'],
            'description' => (string) ($meta['description'] ?? ''),
            'inLanguage' => 'de',
            'datePublished' => self::iso((string) $post['published_at']),
            'dateModified' => self::iso((string) $post['updated_at']),
            'wordCount' => (int) $post['word_count'],
            'publisher' => ['@type' => 'Organization', 'name' => (string) $site->settings['site_title']],
        ];

        $document['author'] = self::author($site);
        if ($post['primary_category'] !== null) {
            $document['articleSection'] = (string) $post['primary_category']['name'];
        }
        if ($post['tags'] !== []) {
            $document['keywords'] = implode(', ', array_column($post['tags'], 'name'));
        }

        $image = is_array($meta['ogImage'] ?? null) ? self::imageObject($meta['ogImage'], $baseUrl) : null;
        if ($image !== null) {
            $document['image'] = $image;
        }

        $citations = self::citations($post['sources']);
        if ($citations !== []) {
            $document['citation'] = $citations;
        }

        return $document;
    }

    /** @return array<string, string> */
    private static function author(SiteData $site): array
    {
        $name = trim((string) $site->settings['author_name']);
        if ($name === '') {
            $name = (string) $site->settings['site_title'];
        }
        $author = ['@type' => 'Person', 'name' => $name];
        $url = trim((string) $site->settings['author_url']);
        if ($url !== '') {
            $author['url'] = $url;
        }

        return $author;
    }

    /**
     * @param array<string, mixed> $media MediaView
     * @return array<string, mixed>|null
     */
    private static function imageObject(array $media, string $baseUrl): ?array
    {
        $image = self::shareImage($media);
        if ($image === null) {
            return null;
        }

        $object = [
            '@type' => 'ImageObject',
            'url' => $baseUrl . '/' . $image['path'],
            'width' => $image['width'],
            'height' => $image['height'],
        ];
        $credit = trim((string) $media['credit']);
        if ($credit !== '') {
            $object['creditText'] = $credit;
            $object['creator'] = ['@type' => 'Person', 'name' => $credit];
            $object['copyrightNotice'] = $credit;
        }
        $licenseUrl = trim((string) $media['license_url']);
        if ($licenseUrl !== '') {
            $object['license'] = $licenseUrl;
        }
        $sourceUrl = trim((string) $media['source_url']);
        if ($sourceUrl !== '') {
            $object['acquireLicensePage'] = $sourceUrl;
        }

        return $object;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, string>>
     */
    private static function citations(array $sources): array
    {
        $citations = [];
        foreach ($sources as $source) {
            $citation = ['@type' => 'CreativeWork', 'name' => (string) $source['title']];
            $address = (string) $source['url'];
            if (preg_match('#^https?://#i', $address) === 1) {
                $citation['url'] = $address;
            }
            $citations[] = $citation;
        }

        return $citations;
    }

    /**
     * @param list<array{name: string, url: string}> $krumen
     * @return array<string, mixed>
     */
    private static function breadcrumbList(array $krumen, string $baseUrl): array
    {
        $items = [];
        foreach (array_values($krumen) as $index => $krume) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $krume['name'],
                'item' => $baseUrl . $krume['url'],
            ];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** UTC-Zeit der Datenbank → ISO 8601 mit Zeitzone. */
    public static function iso(string $utc): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format('c');
    }
}
