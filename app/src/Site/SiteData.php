<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Domain\CategoryRepository;
use Dispatch\Domain\DocumentService;
use Dispatch\Domain\MediaRepository;
use Dispatch\Domain\PostRepository;
use Dispatch\Domain\SettingService;
use Dispatch\Util\FormCheck;
use Dispatch\Util\Text;
use PDO;

/**
 * Alles, was die Seiten brauchen, einmal pro Lauf aus dem Live-Stand gelesen; danach nur lesend.
 */
final class SiteData
{
    private const WORDS_PER_MINUTE = 200;

    /**
     * @param array<string, mixed> $settings
     * @param list<array<string, mixed>> $posts PostView, neueste zuerst
     * @param list<array<string, mixed>> $categories CategoryView, nur aktive
     * @param list<array{name: string, slug: string, url: string, count: int}> $tags
     * @param array<string, array{title: string, markdown: string, url: string}> $documents Schlüssel = public_slug
     * @param array<string, array<string, mixed>> $media MediaView, Schlüssel = uid
     */
    private function __construct(
        public readonly array $settings,
        public readonly array $posts,
        public readonly array $categories,
        public readonly array $tags,
        public readonly array $documents,
        public readonly array $media,
        public readonly int $issueNumber,
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function load(PDO $pdo, array $config): self
    {
        $snapshots = (new PostRepository($pdo))->livePosts();

        $media = self::loadMedia($pdo, $snapshots);
        $categories = self::buildCategories((new CategoryRepository($pdo))->all(false), $snapshots, $media);
        $posts = array_map(
            static fn (array $snapshot): array => self::buildPost($snapshot, $categories['byslug'], $media),
            $snapshots
        );

        return new self(
            (new SettingService($pdo, $config))->all(),
            $posts,
            $categories['list'],
            self::countTags($posts),
            self::loadDocuments(new DocumentService($pdo, $config)),
            $media,
            count($posts),
        );
    }

    /**
     * @param list<array<string, mixed>> $snapshots
     * @return array<string, array<string, mixed>>
     */
    private static function loadMedia(PDO $pdo, array $snapshots): array
    {
        $uids = [];
        foreach ($snapshots as $snapshot) {
            $uids = array_merge($uids, FormCheck::mediaUids($snapshot));
        }

        $views = [];
        foreach ((new MediaRepository($pdo))->findManyByUid(array_values(array_unique($uids))) as $uid => $row) {
            $views[$uid] = [
                'uid' => $uid,
                'kind' => $row['kind'],
                'ext' => $row['ext'],
                'width' => $row['width'],
                'height' => $row['height'],
                'alt' => $row['alt'],
                'caption' => $row['caption'],
                'credit' => $row['credit'],
                'license' => $row['license'],
                'license_url' => $row['license_url'],
                'source_url' => $row['source_url'],
                'variants' => $row['variants'],
                'base_url' => '/media/' . $uid . '/',
            ];
        }

        return $views;
    }

    /**
     * @param list<array<string, mixed>> $rows aktive Kategorien aus der Datenbank
     * @param list<array<string, mixed>> $snapshots
     * @param array<string, array<string, mixed>> $media
     * @return array{list: list<array<string, mixed>>, byslug: array<string, array<string, mixed>>}
     */
    private static function buildCategories(array $rows, array $snapshots, array $media): array
    {
        $slugById = [];
        foreach ($rows as $row) {
            $slugById[$row['id']] = (string) $row['slug'];
        }

        $views = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'];
            if ($parentId !== null && !isset($slugById[$parentId])) {
                continue;
            }

            $slug = (string) $row['slug'];
            $parentSlug = $parentId === null ? null : $slugById[$parentId];
            $views[$slug] = [
                'id' => $row['id'],
                'slug' => $slug,
                'name' => (string) $row['name'],
                'description' => $row['description'],
                'parent_id' => $parentId,
                'parent_slug' => $parentSlug,
                'url' => $parentSlug === null ? '/kategorie/' . $slug . '/' : '/kategorie/' . $parentSlug . '/' . $slug . '/',
                'children' => [],
                'post_count' => 0,
                'newest_post_id' => null,
                'image' => null,
                'position' => $row['position'],
            ];
        }

        $views = self::addPostStatistics($views, $snapshots, $media);
        $views = self::addChildren($views);

        $list = array_values($views);
        usort($list, static fn (array $left, array $right): int => self::byPositionAndName($left, $right));
        $list = array_map(static function (array $view): array {
            unset($view['position']);

            return $view;
        }, $list);

        $bySlug = [];
        foreach ($list as $view) {
            $bySlug[$view['slug']] = $view;
        }

        return ['list' => $list, 'byslug' => $bySlug];
    }

    /**
     * Ein Artikel zählt einmal je Kategorie — auch dann, wenn er in der Kategorie und ihrer Unterkategorie liegt.
     *
     * @param array<string, array<string, mixed>> $views
     * @param list<array<string, mixed>> $snapshots neueste zuerst
     * @param array<string, array<string, mixed>> $media
     * @return array<string, array<string, mixed>>
     */
    private static function addPostStatistics(array $views, array $snapshots, array $media): array
    {
        foreach ($snapshots as $snapshot) {
            $slugs = [];
            foreach ((array) $snapshot['categories'] as $category) {
                $slug = (string) $category['slug'];
                if (!isset($views[$slug])) {
                    continue;
                }
                $slugs[$slug] = true;
                if ($views[$slug]['parent_slug'] !== null) {
                    $slugs[$views[$slug]['parent_slug']] = true;
                }
            }

            $coverUid = $snapshot['cover_media_uid'] ?? null;
            $cover = is_string($coverUid) ? ($media[$coverUid] ?? null) : null;
            foreach (array_keys($slugs) as $slug) {
                $views[$slug]['post_count']++;
                if ($views[$slug]['newest_post_id'] === null) {
                    $views[$slug]['newest_post_id'] = (int) $snapshot['id'];
                    $views[$slug]['image'] = $cover;
                }
            }
        }

        return $views;
    }

    /**
     * @param array<string, array<string, mixed>> $views
     * @return array<string, array<string, mixed>>
     */
    private static function addChildren(array $views): array
    {
        $children = [];
        foreach ($views as $slug => $view) {
            if ($view['parent_slug'] !== null) {
                $children[$view['parent_slug']][] = $view;
            }
        }

        foreach ($children as $parentSlug => $list) {
            usort($list, static fn (array $left, array $right): int => self::byPositionAndName($left, $right));
            $views[$parentSlug]['children'] = array_map(static function (array $child): array {
                unset($child['position']);

                return $child;
            }, $list);
        }

        return $views;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private static function byPositionAndName(array $left, array $right): int
    {
        return [$left['position'], mb_strtolower((string) $left['name'])] <=> [$right['position'], mb_strtolower((string) $right['name'])];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, array<string, mixed>> $categoriesBySlug
     * @param array<string, array<string, mixed>> $media
     * @return array<string, mixed>
     */
    private static function buildPost(array $snapshot, array $categoriesBySlug, array $media): array
    {
        $categories = [];
        $primary = null;
        foreach ((array) $snapshot['categories'] as $entry) {
            $view = $categoriesBySlug[(string) $entry['slug']] ?? null;
            if ($view === null) {
                continue;
            }
            $categories[] = $view;
            if ($entry['primary'] === true && $primary === null) {
                $primary = $view;
            }
        }

        $tags = [];
        foreach ((array) $snapshot['tags'] as $name) {
            $slug = Text::slugify((string) $name);
            $tags[] = ['name' => (string) $name, 'slug' => $slug, 'url' => '/tag/' . $slug . '/'];
        }

        $sources = [];
        foreach (array_values((array) $snapshot['sources']) as $index => $source) {
            $sources[] = ['nr' => $index + 1] + $source;
        }

        $coverUid = $snapshot['cover_media_uid'] ?? null;
        $wordCount = (int) $snapshot['word_count'];

        return [
            'id' => (int) $snapshot['id'],
            'slug' => (string) $snapshot['slug'],
            'url' => '/artikel/' . $snapshot['slug'] . '/',
            'title' => (string) $snapshot['title'],
            'subtitle' => $snapshot['subtitle'],
            'kicker' => $snapshot['kicker'],
            'excerpt' => $snapshot['excerpt'],
            'content_html' => (string) $snapshot['content_html'],
            'layout' => $snapshot['layout'],
            'accent' => $snapshot['accent'],
            'cover' => is_string($coverUid) ? ($media[$coverUid] ?? null) : null,
            'seo_title' => $snapshot['seo_title'],
            'seo_description' => $snapshot['seo_description'],
            'word_count' => $wordCount,
            'reading_minutes' => max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE)),
            'ai_assisted' => (bool) $snapshot['ai_assisted'],
            'published_at' => $snapshot['published_at'],
            'updated_at' => $snapshot['updated_at'],
            'primary_category' => $primary,
            'categories' => $categories,
            'tags' => $tags,
            'sources' => $sources,
        ];
    }

    /**
     * @param list<array<string, mixed>> $posts
     * @return list<array{name: string, slug: string, url: string, count: int}>
     */
    private static function countTags(array $posts): array
    {
        $counted = [];
        foreach ($posts as $post) {
            $seenInPost = [];
            foreach ($post['tags'] as $tag) {
                if (isset($seenInPost[$tag['slug']])) {
                    continue;
                }
                $seenInPost[$tag['slug']] = true;
                if (!isset($counted[$tag['slug']])) {
                    $counted[$tag['slug']] = $tag + ['count' => 0];
                }
                $counted[$tag['slug']]['count']++;
            }
        }

        $tags = array_values($counted);
        usort($tags, static fn (array $left, array $right): int => [$right['count'], mb_strtolower($left['name'])] <=> [$left['count'], mb_strtolower($right['name'])]);

        return $tags;
    }

    /** @return array<string, array{title: string, markdown: string, url: string}> */
    private static function loadDocuments(DocumentService $service): array
    {
        $documents = [];
        foreach ($service->list() as $entry) {
            $publicSlug = (string) ($entry['public_slug'] ?? '');
            if ($publicSlug === '') {
                continue;
            }

            $documents[$publicSlug] = [
                'title' => (string) $entry['title'],
                'markdown' => (string) $service->get((string) $entry['doc_key'])['content'],
                'url' => '/' . $publicSlug . '/',
            ];
        }

        return $documents;
    }
}
