<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

/** Kennzahlen und Einstellungen der Website für Agenten. */
final class SiteInfoService
{
    private SettingService $settings;
    private PostRepository $posts;
    private QueueRepository $queue;
    private MediaRepository $media;
    private TokenRepository $tokens;
    private ChangeRepository $changes;

    /** @param array<string, mixed> $config */
    public function __construct(PDO $pdo, private readonly array $config)
    {
        $this->settings = new SettingService($pdo, $config);
        $this->posts = new PostRepository($pdo);
        $this->queue = new QueueRepository($pdo);
        $this->media = new MediaRepository($pdo);
        $this->tokens = new TokenRepository($pdo);
        $this->changes = new ChangeRepository($pdo);
    }

    /** @return array<string, mixed> */
    public function info(): array
    {
        return [
            'settings' => $this->settings->all(),
            'form_rules' => $this->settings->formRules(),
            'counts' => [
                'posts' => $this->posts->countByStatus(),
                'queue' => $this->queue->countByEffectiveStatus(),
                'changes_pending' => count($this->changes->list('pending')),
                'media' => $this->media->count(),
                'tokens_active' => $this->tokens->countActive(),
            ],
            'last_build_at' => $this->lastBuildAt(),
            'base_url' => $this->config['base_url'],
        ];
    }

    /** Das Manifest schreibt der Builder bei jedem Lauf, seine Änderungszeit ist der letzte Bau. */
    private function lastBuildAt(): ?string
    {
        $path = (string) $this->config['storage_dir'] . '/manifest.json';
        $modified = is_file($path) ? filemtime($path) : false;

        return $modified === false ? null : gmdate('Y-m-d H:i:s', $modified);
    }
}
