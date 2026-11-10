<?php

declare(strict_types=1);

namespace Dispatch\Site;

use DateTimeImmutable;
use DateTimeZone;

use function Dispatch\Site\absolut;

/** RSS 2.0 mit den neuesten Artikeln samt vollem Inhalt. */
final class Feed
{
    private const ITEM_LIMIT = 20;

    /**
     * @param array<int, array{html: string}> $rendered Post-ID → aufbereiteter Inhalt
     */
    public static function build(SiteData $site, array $rendered, string $baseUrl, string $mediaDir): string
    {
        $title = (string) $site->settings['site_title'];
        $description = trim((string) $site->settings['site_description']);
        if ($description === '') {
            $description = (string) $site->settings['site_tagline'];
        }

        $items = '';
        foreach (array_slice($site->posts, 0, self::ITEM_LIMIT) as $post) {
            $items .= self::item($post, (string) $rendered[$post['id']]['html'], $baseUrl, $mediaDir);
        }

        $newest = $site->posts[0]['updated_at'] ?? null;
        $buildDate = $newest === null ? '' : '    <lastBuildDate>' . self::rfc822((string) $newest) . "</lastBuildDate>\n";

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
            . "  <channel>\n"
            . '    <title>' . self::xml($title) . "</title>\n"
            . '    <link>' . self::xml($baseUrl . '/') . "</link>\n"
            . '    <description>' . self::xml($description) . "</description>\n"
            . "    <language>de</language>\n"
            . $buildDate
            . '    <atom:link href="' . self::xml($baseUrl . '/feed.xml') . '" rel="self" type="application/rss+xml"/>' . "\n"
            . $items
            . "  </channel>\n</rss>\n";
    }

    /** @param array<string, mixed> $post */
    private static function item(array $post, string $html, string $baseUrl, string $mediaDir): string
    {
        $link = $baseUrl . $post['url'];
        $item = "    <item>\n"
            . '      <title>' . self::xml((string) $post['title']) . "</title>\n"
            . '      <link>' . self::xml($link) . "</link>\n"
            . '      <guid isPermaLink="true">' . self::xml($link) . "</guid>\n"
            . '      <pubDate>' . self::rfc822((string) $post['published_at']) . "</pubDate>\n"
            . '      <description>' . self::xml((string) $post['excerpt']) . "</description>\n";

        foreach ($post['categories'] as $category) {
            $item .= '      <category>' . self::xml((string) $category['name']) . "</category>\n";
        }
        $item .= self::enclosure($post['cover'], $baseUrl, $mediaDir);

        // `]]>` würde den CDATA-Abschnitt beenden: in zwei Abschnitte auftrennen.
        $content = str_replace(']]>', ']]]]><![CDATA[>', absolut($html, $baseUrl));

        return $item . '      <content:encoded><![CDATA[' . $content . "]]></content:encoded>\n    </item>\n";
    }

    /** @param array<string, mixed>|null $cover MediaView */
    private static function enclosure(?array $cover, string $baseUrl, string $mediaDir): string
    {
        if ($cover === null) {
            return '';
        }
        $image = Seo::shareImage($cover);
        if ($image === null) {
            return '';
        }

        $file = $mediaDir . '/' . substr($image['path'], strlen('media/'));
        $size = is_file($file) ? (int) filesize($file) : 0;

        return '      <enclosure url="' . self::xml($baseUrl . '/' . $image['path']) . '" length="' . $size . '" type="' . $image['mime'] . '"/>' . "\n";
    }

    private static function rfc822(string $utc): string
    {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format('D, d M Y H:i:s \G\M\T');
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
