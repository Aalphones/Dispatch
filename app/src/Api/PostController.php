<?php

declare(strict_types=1);

namespace Dispatch\Api;

use DateTimeImmutable;
use DateTimeZone;
use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\PreviewLink;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaRepository;
use Dispatch\Domain\PostService;
use Dispatch\Domain\ReactionService;
use Dispatch\Domain\ScheduleService;
use Dispatch\Domain\StatsService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Site\Builder;
use Dispatch\Util\Diff;
use Dispatch\Util\FormCheck;
use PDO;

/** Artikel-Übersicht und Freigabe-Aktionen des Inhabers (ADR-021). */
final class PostController
{
    private const STATUSES = ['draft', 'review', 'published', 'archived', 'scheduled'];
    private const LIST_LIMIT = 200;
    private const STATS_DAYS = 30;

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
                    'author_model' => $row['author_model'],
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
            $stats = (new StatsService(Connection::open()))->postReport((int) $post['id'], self::STATS_DAYS);
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
            'stats' => $stats,
        ]);
    }

    public function approve(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            [$when, $at] = $this->readWhen($request);
            $post = $this->posts()->approve($id, Actor::owner(), $when, $at);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        $this->respondScheduledOrRebuilt($post);
    }

    public function schedule(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            [$when, $at] = $this->readWhen($request);
            $post = $this->posts()->reschedule($id, $when, $at, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        $this->respondScheduledOrRebuilt($post);
    }

    public function unschedule(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $post = $this->posts()->unschedule($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['post' => $this->summary($post)]);
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

    /** Arbeitsstand gegenüber der Live-Fassung: Text, Quellen, Medien (ADR-021). */
    public function diff(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $pdo = Connection::open();
            $posts = new PostService($pdo, $this->config);

            $post = $posts->get($id);
            if ($post['published_revision'] === null || $post['status'] === 'archived') {
                throw new DomainError('no_live_version', 'Der Artikel hat keine Live-Fassung, mit der sich vergleichen ließe.', ['id' => $id]);
            }

            $live = $posts->revision($id, (int) $post['published_revision']);
            $draft = $posts->previewSnapshot($id);

            $text = Diff::html((string) $live['content_html'], (string) $draft['content_html']);
            $sources = $this->sourceChanges((array) $live['sources'], (array) $draft['sources']);
            $media = $this->mediaChanges($pdo, $live, $draft);
            $html = PreviewLink::protectMedia((new Builder($this->config, $pdo))->previewMain(['content_html' => $text['html']] + $draft), $id, FormCheck::mediaUids($draft));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json([
            'html' => $html,
            'sources' => $sources,
            'media' => $media,
            'summary' => [
                'blocks_changed' => $text['blocks_changed'],
                'sources_added' => count($sources['added']),
                'sources_removed' => count($sources['removed']),
                'media_changed' => count($media['added']) + count($media['removed']),
            ],
        ]);
    }

    /**
     * Schlüssel einer Quelle ist die URL, ohne URL der Titel.
     *
     * @param list<array<string, mixed>> $live
     * @param list<array<string, mixed>> $draft
     * @return array{added: list<array{title: string, url: string}>, removed: list<array{title: string, url: string}>}
     */
    private function sourceChanges(array $live, array $draft): array
    {
        $liveByKey = $this->sourcesByKey($live);
        $draftByKey = $this->sourcesByKey($draft);

        return [
            'added' => array_values(array_diff_key($draftByKey, $liveByKey)),
            'removed' => array_values(array_diff_key($liveByKey, $draftByKey)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, array{title: string, url: string}>
     */
    private function sourcesByKey(array $sources): array
    {
        $byKey = [];
        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            $title = (string) ($source['title'] ?? '');
            $byKey[$url !== '' ? $url : $title] = ['title' => $title, 'url' => $url];
        }

        return $byKey;
    }

    /**
     * Medien-UIDs aus Titelbild und `data-media`; `alt` stammt aus der Mediathek.
     *
     * @param array<string, mixed> $live
     * @param array<string, mixed> $draft
     * @return array{added: list<array{uid: string, alt: string}>, removed: list<array{uid: string, alt: string}>}
     */
    private function mediaChanges(PDO $pdo, array $live, array $draft): array
    {
        $liveUids = FormCheck::mediaUids($live);
        $draftUids = FormCheck::mediaUids($draft);
        $addedUids = array_values(array_diff($draftUids, $liveUids));
        $removedUids = array_values(array_diff($liveUids, $draftUids));

        $known = (new MediaRepository($pdo))->findManyByUid(array_merge($addedUids, $removedUids));
        $describe = static fn (string $uid): array => ['uid' => $uid, 'alt' => (string) ($known[$uid]['alt'] ?? '')];

        return [
            'added' => array_map($describe, $addedUids),
            'removed' => array_map($describe, $removedUids),
        ];
    }

    /**
     * Kein Body heißt „sofort“ — so verhält sich jeder ältere Aufrufer wie bisher.
     *
     * @return array{0: string, 1: DateTimeImmutable|null} Zeitpunkt-Art und, bei festem Termin, der Termin in UTC
     * @throws DomainError bei ungültigem JSON oder ungültigem `at`
     */
    private function readWhen(Request $request): array
    {
        if (trim($request->body()) === '') {
            return ['now', null];
        }

        $input = $request->json();
        $when = (string) ($input['when'] ?? 'now');
        $at = null;
        if (isset($input['at'])) {
            $at = ScheduleService::parseAt((string) $input['at'], new DateTimeImmutable('now', new DateTimeZone('UTC')));
        }

        return [$when, $at];
    }

    /**
     * Ein eingeplanter Artikel ändert die Website nicht, also gibt es keinen Neubau.
     *
     * @param array<string, mixed> $post
     */
    private function respondScheduledOrRebuilt(array $post): never
    {
        if ($post['status'] === 'scheduled') {
            Response::json(['post' => $this->summary($post), 'rebuilt' => null, 'rebuild_error' => null]);
        }

        Response::json(['post' => $this->summary($post)] + Rebuild::run($this->config, Connection::open()));
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
            'schedule_mode' => $post['schedule_mode'] ?? null,
            'scheduled_at' => $post['scheduled_at'] ?? null,
            'review_note' => $post['review_note'],
        ];
    }
}
