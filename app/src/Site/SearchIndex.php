<?php

declare(strict_types=1);

namespace Dispatch\Site;

/** Der Suchindex für `suche.js` (Format: README des Meilensteins 2, „Kontrakt“). */
final class SearchIndex
{
    private const VERSION = 1;
    private const THUMBNAIL_WIDTH = 480;

    /**
     * @param array<int, array{headings: list<string>, text: string}> $rendered Ergebnis des ContentRenderer je Post-ID
     */
    public static function build(SiteData $data, array $rendered): string
    {
        $categories = [];
        foreach ($data->categories as $category) {
            $categories[] = [
                's' => $category['slug'],
                'n' => $category['name'],
                'u' => $category['url'],
                'e' => $category['parent_slug'],
            ];
        }

        $tags = [];
        foreach ($data->tags as $tag) {
            if ($tag['slug'] === '') {
                continue;
            }
            $tags[] = ['s' => $tag['slug'], 'n' => $tag['name'], 'u' => $tag['url'], 'c' => $tag['count']];
        }

        $articles = [];
        foreach ($data->posts as $post) {
            $content = $rendered[$post['id']] ?? ['headings' => [], 'text' => ''];
            $articles[] = [
                'i' => $post['id'],
                'u' => $post['url'],
                't' => $post['title'],
                'z' => (string) $post['subtitle'],
                'x' => (string) $post['excerpt'],
                'h' => $content['headings'],
                'k' => self::categorySlugs($post),
                'g' => array_values(array_filter(array_column($post['tags'], 'slug'), static fn (string $slug): bool => $slug !== '')),
                'd' => substr((string) $post['published_at'], 0, 10),
                'b' => self::thumbnailUrl($post['cover']),
                'c' => $content['text'],
            ];
        }

        return json_encode(
            ['v' => self::VERSION, 'kategorien' => $categories, 'tags' => $tags, 'artikel' => $articles],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Kategorie-Slugs inklusive der Eltern, damit der Filter auf eine Oberkategorie Unterkategorien einschließt.
     *
     * @param array<string, mixed> $post
     * @return list<string>
     */
    private static function categorySlugs(array $post): array
    {
        $slugs = [];
        foreach ($post['categories'] as $category) {
            $slugs[] = (string) $category['slug'];
            if ($category['parent_slug'] !== null) {
                $slugs[] = (string) $category['parent_slug'];
            }
        }

        return array_values(array_unique($slugs));
    }

    /** Die WebP-Variante, die 480 px am nächsten kommt (die kleinste ab 480, sonst die größte). */
    private static function thumbnailUrl(?array $cover): ?string
    {
        if ($cover === null) {
            return null;
        }

        $best = null;
        $bestWidth = 0;
        foreach ((array) $cover['variants'] as $variant) {
            foreach ((array) $variant['files'] as $file) {
                if (!str_ends_with((string) $file, '.webp')) {
                    continue;
                }
                $width = (int) $variant['w'];
                $isBetter = $best === null
                    || ($bestWidth < self::THUMBNAIL_WIDTH && $width > $bestWidth)
                    || ($width >= self::THUMBNAIL_WIDTH && $width < $bestWidth);
                if ($isBetter) {
                    $best = (string) $file;
                    $bestWidth = $width;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return $cover['base_url'] . $best;
    }
}
