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
    // Formvorgaben und Betriebsmodi: nur hier änderbar, nie per Service (SettingService → not_changeable).
    'form_rules' => [
        'min_sources' => 3,
        'min_sources_deep' => 5,
        'min_words' => 600,
        'min_words_deep' => 1200,
        'require_cover' => true,
        'require_image_license' => true,
    ],
    'publish_mode' => 'review',
    'stats_enabled' => true,
];
