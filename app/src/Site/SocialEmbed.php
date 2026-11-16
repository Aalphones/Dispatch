<?php

declare(strict_types=1);

namespace Dispatch\Site;

/**
 * Prüft die Adresse eines Social-Beitrags streng je Plattform und baut daraus die Adresse des Beitrags-iframes (ADR-033).
 *
 * Nur https, nur die Hosts der Plattform, die ID muss dem festen Muster entsprechen. Alles andere ergibt null und wird
 * nie zum iframe. Die Klasse lädt nichts; sie rechnet nur Zeichenketten um.
 */
final class SocialEmbed
{
    /** @var array<string, array{label: string, height: int}> */
    public const PROVIDERS = [
        'x' => ['label' => 'X', 'height' => 520],
        'instagram' => ['label' => 'Instagram', 'height' => 640],
        'facebook' => ['label' => 'Facebook', 'height' => 560],
        'tiktok' => ['label' => 'TikTok', 'height' => 740],
    ];

    private const HOSTS = [
        'x' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'],
        'instagram' => ['instagram.com', 'www.instagram.com'],
        'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com'],
        'tiktok' => ['tiktok.com', 'www.tiktok.com'],
    ];

    /**
     * @return array{src: string, height: int, label: string, url: string}|null
     */
    public static function parse(string $provider, string $url): ?array
    {
        if (!isset(self::PROVIDERS[$provider])) {
            return null;
        }

        $url = trim($url);
        if ($url === '' || preg_match('/[\s<>"\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, self::HOSTS[$provider], true)) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $src = match ($provider) {
            'x' => self::x($path),
            'instagram' => self::instagram($path),
            'facebook' => self::facebook($path, $query),
            'tiktok' => self::tiktok($path),
        };
        if ($src === null) {
            return null;
        }

        return ['src' => $src, 'height' => self::PROVIDERS[$provider]['height'], 'label' => self::PROVIDERS[$provider]['label'], 'url' => $url];
    }

    private static function x(string $path): ?string
    {
        if (preg_match('~^/(?:[A-Za-z0-9_]{1,15}|i/web)/status/([0-9]{1,20})/?(?:photo/[1-4]|video/[1-4])?/?\z~', $path, $match) !== 1) {
            return null;
        }

        return 'https://platform.twitter.com/embed/Tweet.html?id=' . $match[1];
    }

    private static function instagram(string $path): ?string
    {
        if (preg_match('~^/(?:[A-Za-z0-9._]{1,30}/)?(p|reel|tv)/([A-Za-z0-9_-]{5,30})/?\z~', $path, $match) !== 1) {
            return null;
        }

        return 'https://www.instagram.com/' . $match[1] . '/' . $match[2] . '/embed/';
    }

    /** @param array<array-key, mixed> $query */
    private static function facebook(string $path, array $query): ?string
    {
        $isVideo = false;
        if (preg_match('~^/[A-Za-z0-9.\-]{1,80}/(?:posts|videos)/[A-Za-z0-9_.:\-]{1,120}/?\z~', $path) === 1
            || preg_match('~^/(?:reel|photo|permalink\.php|story\.php)/?[0-9]*/?\z~', $path) === 1) {
            $normalised = 'https://www.facebook.com' . rtrim($path, '/');
            $isVideo = str_contains($path, '/videos/') || str_starts_with($path, '/reel/');
            if (str_ends_with($path, '.php')) {
                $id = (string) ($query['story_fbid'] ?? '');
                $owner = (string) ($query['id'] ?? '');
                if (preg_match('/^[A-Za-z0-9_]{1,60}\z/', $id) !== 1 || preg_match('/^[0-9]{1,30}\z/', $owner) !== 1) {
                    return null;
                }
                $normalised .= '?story_fbid=' . $id . '&id=' . $owner;
            }
        } elseif ($path === '/watch' || $path === '/watch/') {
            $id = (string) ($query['v'] ?? '');
            if (preg_match('/^[0-9]{1,30}\z/', $id) !== 1) {
                return null;
            }
            $normalised = 'https://www.facebook.com/watch/?v=' . $id;
            $isVideo = true;
        } else {
            return null;
        }

        return 'https://www.facebook.com/plugins/' . ($isVideo ? 'video' : 'post') . '.php?href=' . rawurlencode($normalised) . '&show_text=true&width=550';
    }

    private static function tiktok(string $path): ?string
    {
        if (preg_match('~^/@[A-Za-z0-9._]{1,40}/video/([0-9]{1,25})/?\z~', $path, $match) !== 1) {
            return null;
        }

        return 'https://www.tiktok.com/embed/v2/' . $match[1];
    }
}
