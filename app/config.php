<?php

declare(strict_types=1);

use Dispatch\Util\Env;

return [
    'base_url' => Env::get('DISPATCH_BASE_URL'),
    'app_dir' => __DIR__,
    'storage_dir' => __DIR__ . '/storage',
    'web_root' => Env::get('DISPATCH_WEB_ROOT') ?: dirname(__DIR__),
    // Namen der obersten Ebene, die der Builder nie beschreibt (ADR-001).
    'reserved_top_level' => ['card', 'api', 'backend', 'scanner', 'dispatch', 'dispatch-app', '.htaccess', '.well-known'],
    // ADR-014: der Builder schreibt und löscht nur unter diesen Namen der obersten Ebene.
    'builder_top_level' => [
        'index.html', '404.html', 'feed.xml', 'sitemap.xml', 'robots.txt', 'seite', 'artikel', 'kategorie',
        'kategorien', 'tag', 'suche', 'vorschau', 'ueber', 'impressum', 'datenschutz', 'assets', 'media',
    ],
    // Formvorgaben und Betriebsmodi: nur hier änderbar, nie per Service (SettingService → not_changeable).
    'form_rules' => [
        'min_sources' => 3,
        'min_sources_deep' => 5,
        'min_words' => 600,
        'min_words_deep' => 1200,
        'require_cover' => true,
        'require_image_license' => true,
    ],
    'media' => [
        'max_bytes' => 50 * 1024 * 1024,
        'max_megapixels' => 50,
        'variant_widths' => [480, 800, 1200, 1600, 2000],
        'jpeg_quality' => 82,
        'webp_quality' => 80,
    ],
    // allow_private nur lokal (dev.env.example), auf Strato nie gesetzt.
    'http' => [
        'connect_timeout' => 5,
        'timeout' => 20,
        'max_redirects' => 4,
        'allow_private' => Env::get('DISPATCH_ALLOW_PRIVATE_IPS') === '1',
    ],
    // OAuth 2.1 für die Claude-Apps (ADR-024).
    'oauth' => [
        'cimd_hosts' => ['claude.ai'],          // Allowlist für CIMD-client_id (exakter Host)
        'cimd_max_bytes' => 65536,
        'cimd_cache_seconds' => 86400,
        // Eigener HttpClient für CIMD; Claude wartet höchstens 10 s.
        'http' => ['connect_timeout' => 3, 'timeout' => 4, 'max_redirects' => 0],
        'request_seconds' => 600,               // Anfrage bis zur Zustimmung
        'code_seconds' => 300,
        'access_seconds' => 3600,
        'refresh_seconds' => 5184000,           // 60 Tage, bei jeder Erneuerung neu
        'grace_seconds' => 30,
        'default_scopes' => ['blog:read', 'blog:write'],
        'manual_redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ],
    'publish_mode' => 'review',
    'stats_enabled' => true,
    // Anfragen, die zu diesem User-Agent-Muster passen (oder keinen haben), zählen nicht.
    'stats_bot_pattern' => '/bot|crawl|spider|slurp|preview|headless|lighthouse|curl|wget|python|httpclient|java\//i',
];
