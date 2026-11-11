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
    'publish_mode' => 'review',
    'stats_enabled' => true,
];
