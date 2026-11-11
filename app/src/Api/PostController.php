<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\PostService;
use Dispatch\Domain\ReactionService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** Artikel-Übersicht und Freigabe-Aktionen des Inhabers (ADR-021). */
final class PostController
{
    private const STATUSES = ['draft', 'review', 'published', 'archived'];
    private const LIST_LIMIT = 200;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $statuses = $this->statuses($request->query('status'));
            $posts = $this->posts();

            $entries = [];
            foreach ($posts->list($statuses, self::LIST_LIMIT) as $row) {
                $form = $posts->validate((int) $row['id']);
                $entries[] = [
                    'id' => (int) $row['id'],
                    'slug' => $row['slug'],
                    'title' => $row['title'],
                    'status' => $row['status'],
                    'revision' => (int) $row['revision'],
                    'published_revision' => $row['published_revision'] === null ? null : (int) $row['published_revision'],
                    'updated_at' => $row['updated_at'],
                    'word_count' => (int) $row['word_count'],
                    'sources' => $form->metrics['sources'],
                    'review_note' => $row['review_note'],
                    'form' => ['errors' => count($form->errors), 'warnings' => count($form->warnings)],
                ];
            }
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['posts' => $entries]);
    }

    public function bySlug(Request $request, string $slug): never
    {
        ApiAuth::owner($request);

        try {
            $post = $this->posts()->getBySlug($slug);
            $reaction = (new ReactionService(Connection::open()))->get((int) $post['id']);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        $publishedRevision = $post['published_revision'];
        Response::json([
            'id' => $post['id'],
            'status' => $post['status'],
            'revision' => $post['revision'],
            'published_revision' => $publishedRevision,
            'has_new_version' => $publishedRevision !== null && $post['revision'] > $publishedRevision && $post['status'] === 'review',
            'reaction' => $reaction,
            'stats' => null,
        ]);
    }

    public function approve(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $post = $this->posts()->approve($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['post' => $this->summary($post)] + Rebuild::run($this->config, Connection::open()));
    }

    public function return(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $note = $request->json()['note'] ?? null;
            if (!is_string($note)) {
                throw new DomainError('invalid_value', 'note fehlt (Text, was am Entwurf zu ändern ist).', ['field' => 'note']);
            }
            $post = $this->posts()->returnToAgent($id, $note, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['post' => $this->summary($post)]);
    }

    public function unpublish(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $post = $this->posts()->unpublish($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['post' => $this->summary($post)] + Rebuild::run($this->config, Connection::open()));
    }

    public function discard(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $this->posts()->discard($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }

    private function posts(): PostService
    {
        return new PostService(Connection::open(), $this->config);
    }

    /**
     * @return list<string> leer = alle
     * @throws DomainError bei einem unbekannten Status
     */
    private function statuses(?string $parameter): array
    {
        if ($parameter === null || $parameter === '') {
            return [];
        }

        $statuses = explode(',', $parameter);
        foreach ($statuses as $status) {
            if (!in_array($status, self::STATUSES, true)) {
                throw new DomainError(
                    'invalid_value',
                    sprintf('Der Status „%s“ ist unbekannt. Gültig: %s.', $status, implode(', ', self::STATUSES)),
                    ['field' => 'status']
                );
            }
        }

        return $statuses;
    }

    /**
     * Der Artikel ohne HTML — die Antwort einer Aktion braucht den neuen Stand, nicht den Text.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function summary(array $post): array
    {
        return [
            'id' => $post['id'],
            'slug' => $post['slug'],
            'title' => $post['title'],
            'status' => $post['status'],
            'revision' => $post['revision'],
            'published_revision' => $post['published_revision'],
            'published_at' => $post['published_at'],
            'review_note' => $post['review_note'],
        ];
    }
}
