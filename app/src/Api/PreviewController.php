<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\PreviewLink;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaRepository;
use Dispatch\Domain\PostService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Site\Builder;
use Dispatch\Util\FormCheck;

/**
 * Vorschau eines Arbeitsstands: dieselbe Darstellung wie die Website (`Builder::articleMain`), nie in die Wurzel geschrieben.
 * Mit Anmeldung unter `posts/{id}/preview`, mit signiertem Link unter `preview`.
 */
final class PreviewController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function byLogin(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            Response::json($this->payload($id) + ['stats' => null]);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }
    }

    public function bySignature(Request $request): never
    {
        $id = $this->intParameter($request, 'id');
        $expires = $this->intParameter($request, 'exp');
        $signature = $request->query('sig') ?? '';
        if ($id === null || $expires === null || !PreviewLink::verify($id, $expires, $signature)) {
            Response::error('invalid_signature', 'Der Vorschau-Link ist ungültig oder abgelaufen.', 403);
        }

        try {
            Response::json($this->payload($id));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }
    }

    /** Bild eines Arbeitsstands, nur mit gültiger Vorschau-Signatur und nur, wenn der Artikel es nutzt. */
    public function media(Request $request): never
    {
        $id = $this->intParameter($request, 'id');
        $expires = $this->intParameter($request, 'exp');
        if ($id === null || $expires === null || !PreviewLink::verify($id, $expires, $request->query('sig') ?? '')) {
            Response::error('invalid_signature', 'Der Link ist ungültig oder abgelaufen.', 403);
        }

        $parts = explode('/', (string) ($request->query('f') ?? ''));
        if (count($parts) !== 2 || preg_match('/^[a-z0-9]+$/', $parts[0]) !== 1 || preg_match('/^[A-Za-z0-9._-]+$/', $parts[1]) !== 1) {
            Response::error('not_found', 'Datei nicht gefunden.', 404);
        }
        [$uid, $name] = $parts;

        try {
            $pdo = Connection::open();
            $snapshot = (new PostService($pdo, $this->config))->previewSnapshot($id);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        $medium = in_array($uid, FormCheck::mediaUids($snapshot), true) ? (new MediaRepository($pdo))->findManyByUid([$uid])[$uid] ?? null : null;
        $allowed = [];
        if ($medium !== null) {
            $allowed = $medium['kind'] === 'image' ? [] : ['original.' . $medium['ext']];
            foreach ((array) $medium['variants'] as $variant) {
                foreach ((array) $variant['files'] as $file) {
                    $allowed[] = (string) $file;
                }
            }
        }
        $path = (string) $this->config['storage_dir'] . '/media/' . $uid . '/' . $name;
        if (!in_array($name, $allowed, true) || !is_file($path)) {
            Response::error('not_found', 'Datei nicht gefunden.', 404);
        }

        $types = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'avif' => 'image/avif', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
        $type = $types[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
        if ($type === null) {
            Response::error('not_found', 'Datei nicht gefunden.', 404);
        }

        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** @return array<string, mixed> */
    private function payload(int $id): array
    {
        $pdo = Connection::open();
        $posts = new PostService($pdo, $this->config);

        $post = $posts->get($id);
        $form = $posts->validate($id);
        $snapshot = $posts->previewSnapshot($id);

        return [
            'post' => [
                'id' => $post['id'],
                'slug' => $post['slug'],
                'title' => $post['title'],
                'status' => $post['status'],
                'revision' => $post['revision'],
                'published_revision' => $post['published_revision'],
                'review_note' => $post['review_note'],
                'author_model' => $post['author_model'],
                'word_count' => $post['word_count'],
                'sources' => count($post['sources']),
                'updated_at' => $snapshot['updated_at'],
            ],
            'html' => PreviewLink::protectMedia((new Builder($this->config, $pdo))->previewMain($snapshot), $id, FormCheck::mediaUids($snapshot)),
            'form' => [
                'errors' => $this->findings($form->errors),
                'warnings' => $this->findings($form->warnings),
            ],
            'has_live' => $post['published_revision'] !== null && $post['status'] !== 'archived',
        ];
    }

    /**
     * @param list<array{code: string, field: string, message: string}> $findings
     * @return list<array{code: string, message: string}>
     */
    private function findings(array $findings): array
    {
        return array_map(
            static fn (array $finding): array => ['code' => $finding['code'], 'message' => $finding['message']],
            $findings
        );
    }

    private function intParameter(Request $request, string $name): ?int
    {
        $value = $request->query($name);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
