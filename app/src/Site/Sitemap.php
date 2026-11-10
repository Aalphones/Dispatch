<?php

declare(strict_types=1);

namespace Dispatch\Site;

/** sitemap.xml mit allen indexierbaren Seiten; Seitenzahlen, Suche und Dokumente mit noindex fehlen bewusst. */
final class Sitemap
{
    public static function build(SiteData $site, string $baseUrl): string
    {
        $entries = [];
        $newestOverall = $site->posts[0]['updated_at'] ?? null;
        $entries[] = self::entry('/', self::latest($site->posts), []);

        foreach ($site->posts as $post) {
            $images = [];
            $image = is_array($post['cover']) ? Seo::shareImage($post['cover']) : null;
            if ($image !== null) {
                $images[] = $baseUrl . '/' . $image['path'];
            }
            $entries[] = self::entry($post['url'], (string) $post['updated_at'], $images);
        }

        $entries[] = self::entry('/kategorien/', self::latest($site->posts), []);
        foreach ($site->categories as $category) {
            $entries[] = self::entry($category['url'], self::latest(self::postsOfCategory($site, $category)), []);
        }
        foreach ($site->tags as $tag) {
            if ($tag['slug'] === '') {
                continue;
            }
            $tagged = array_filter($site->posts, static fn (array $post): bool => in_array($tag['slug'], array_column($post['tags'], 'slug'), true));
            $entries[] = self::entry($tag['url'], self::latest(array_values($tagged)), []);
        }
        if (isset($site->documents['ueber'])) {
            $entries[] = self::entry($site->documents['ueber']['url'], $newestOverall === null ? null : (string) $newestOverall, []);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($entries as $entry) {
            $xml .= self::render($entry, $baseUrl);
        }

        return $xml . "</urlset>\n";
    }

    /**
     * @param list<array<string, mixed>> $posts
     */
    private static function latest(array $posts): ?string
    {
        $latest = null;
        foreach ($posts as $post) {
            if ($latest === null || (string) $post['updated_at'] > $latest) {
                $latest = (string) $post['updated_at'];
            }
        }

        return $latest;
    }

    /**
     * @param array<string, mixed> $category
     * @return list<array<string, mixed>>
     */
    private static function postsOfCategory(SiteData $site, array $category): array
    {
        return array_values(array_filter(
            $site->posts,
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

    /**
     * @param list<string> $images absolute Adressen
     * @return array{url: string, lastmod: string|null, images: list<string>}
     */
    private static function entry(string $url, ?string $lastModifiedUtc, array $images): array
    {
        return ['url' => $url, 'lastmod' => $lastModifiedUtc === null ? null : Seo::iso($lastModifiedUtc), 'images' => $images];
    }

    /** @param array{url: string, lastmod: string|null, images: list<string>} $entry */
    private static function render(array $entry, string $baseUrl): string
    {
        $xml = "  <url>\n    <loc>" . self::xml($baseUrl . $entry['url']) . "</loc>\n";
        if ($entry['lastmod'] !== null) {
            $xml .= '    <lastmod>' . $entry['lastmod'] . "</lastmod>\n";
        }
        foreach ($entry['images'] as $image) {
            $xml .= '    <image:image><image:loc>' . self::xml($image) . "</image:loc></image:image>\n";
        }

        return $xml . "  </url>\n";
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
