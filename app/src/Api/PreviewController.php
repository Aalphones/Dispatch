<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\PreviewLink;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\PostService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Site\Builder;

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
                'word_count' => $post['word_count'],
                'sources' => count($post['sources']),
                'updated_at' => $snapshot['updated_at'],
            ],
            'html' => (new Builder($this->config, $pdo))->previewMain($snapshot),
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
