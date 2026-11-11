<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use Dispatch\Util\FormCheck;
use Dispatch\Util\FormCheckResult;
use Dispatch\Util\Sanitizer;
use Dispatch\Util\Text;
use PDO;

final class PostService
{
    private const FIELDS = [
        'title', 'subtitle', 'kicker', 'excerpt', 'slug', 'content_html', 'tags', 'layout', 'accent',
        'cover_media_uid', 'seo_title', 'seo_description', 'origin', 'depth', 'editor_notes', 'ai_assisted',
        'categories', 'sources',
    ];

    private const OPTIONAL_TEXT_LENGTHS = [
        'subtitle' => 300,
        'kicker' => 80,
        'excerpt' => 400,
        'seo_title' => 200,
        'seo_description' => 300,
    ];

    private const CREATE_DEFAULTS = [
        'tags' => [],
        'layout' => 'standard',
        'origin' => 'manual',
        'depth' => 'standard',
        'ai_assisted' => true,
    ];

    private const LAYOUTS = ['standard', 'feature', 'longform', 'briefing'];
    private const DEPTHS = ['standard', 'deep'];
    private const ORIGINS = ['scheduled', 'manual'];
    private const SOURCE_KINDS = ['web', 'news', 'study', 'official', 'dataset', 'book', 'interview', 'video', 'podcast', 'social', 'other'];

    private const REVIEW_NOTE_MAX_LENGTH = 20000;
    private const SEARCH_MIN_LENGTH = 2;
    private const SEARCH_MAX_LIMIT = 50;
    private const TITLE_MAX_LENGTH = 200;

    /** Felder des Snapshots, die restoreRevision zurückschreibt; Slug und Herkunft bleiben. */
    private const RESTORABLE_FIELDS = [
        'title', 'subtitle', 'kicker', 'excerpt', 'content_html', 'tags', 'layout', 'accent',
        'seo_title', 'seo_description', 'editor_notes', 'sources',
    ];
    private const SLUG_MAX_LENGTH = 120;
    private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
    private const ACCENT_PATTERN = '/^#[0-9a-fA-F]{6}$/';
    private const REF_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,59}\z/';
    private const PUBLISHED_PATTERN = '/^\d{4}(-\d{2}(-\d{2})?)?$/';
    private const TAG_MAX_LENGTH = 40;
    private const TAGS_MAX = 10;
    private const EDITOR_NOTES_MAX_LENGTH = 100000;
    private const SOURCE_TITLE_MAX_LENGTH = 500;
    private const SOURCE_URL_MAX_LENGTH = 2000;
    private const SOURCE_TEXT_MAX_LENGTH = 300;
    private const SOURCE_NOTE_MAX_LENGTH = 10000;

    private PostRepository $posts;
    private CategoryRepository $categories;
    private MediaRepository $media;
    private AuditRepository $audit;
    private QueueService $queue;
    private Sanitizer $sanitizer;
    private FormCheck $formCheck;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->posts = new PostRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
        $this->media = new MediaRepository($pdo);
        $this->audit = new AuditRepository($pdo);
        $this->queue = new QueueService($pdo, $config);
        $this->sanitizer = new Sanitizer();
        $this->formCheck = new FormCheck();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{post: array<string, mixed>, removed: list<string>}
     */
    public function create(array $input, Actor $actor): array
    {
        foreach (['title', 'content_html'] as $required) {
            if (!array_key_exists($required, $input)) {
                throw $this->invalid($required, 'ist beim Anlegen Pflicht');
            }
        }

        $queueItemId = $this->extractQueueItemId($input);
        $normalized = $this->normalize(array_replace(self::CREATE_DEFAULTS, $input), null, $actor);

        return Transaction::join($this->pdo, function () use ($normalized, $queueItemId, $actor): array {
            $columns = $normalized['columns'];
            if ($queueItemId !== null) {
                $item = $this->queue->assertClaimant($queueItemId, $actor);
                $columns['origin'] = 'queue';
                $columns['queue_item_id'] = $queueItemId;
                $columns['depth'] = $item['depth'];
            }
            $columns['slug'] ??= $this->uniqueSlug(Text::slugify($columns['title']));
            $columns['status'] = 'draft';
            $columns['revision'] = 1;
            $postId = $this->posts->insert($columns);
            if ($queueItemId !== null) {
                $this->queue->linkPost($queueItemId, $postId);
            }

            $this->posts->replaceCategories($postId, $normalized['categories'] ?? []);
            $this->posts->replaceSources($postId, $normalized['sources'] ?? []);
            $this->posts->insertRevision($postId, 1, $this->snapshot($postId), $actor->name);
            $this->audit->record($actor, 'post.create', 'post:' . $postId, ['slug' => $columns['slug'], 'revision' => 1]);

            return ['post' => $this->get($postId), 'removed' => $normalized['removed']];
        });
    }

    /**
     * @param array<string, mixed> $patch
     * @return array{post: array<string, mixed>, removed: list<string>}
     */
    public function update(int $id, array $patch, Actor $actor): array
    {
        $current = $this->posts->find($id);
        if ($current === null) {
            throw $this->notFound($id);
        }
        if ($current['status'] === 'archived') {
            throw new DomainError('archived', 'Der Artikel ist archiviert und lässt sich nicht mehr ändern.', ['id' => $id]);
        }

        $normalized = $this->normalize($patch, $id, $actor, $current);

        return Transaction::join($this->pdo, function () use ($id, $current, $normalized, $actor): array {
            $revision = (int) $current['revision'] + 1;
            $columns = $normalized['columns'];
            $columns['revision'] = $revision;
            if ($current['status'] === 'review' && !$actor->isOwner()) {
                $columns['status'] = 'draft';
            }
            $this->posts->update($id, $columns);

            if (isset($normalized['categories'])) {
                $this->posts->replaceCategories($id, $normalized['categories']);
            }
            if (isset($normalized['sources'])) {
                $this->posts->replaceSources($id, $normalized['sources']);
            }
            $this->posts->insertRevision($id, $revision, $this->snapshot($id), $actor->name);
            $this->audit->record($actor, 'post.update', 'post:' . $id, [
                'revision' => $revision,
                'fields' => array_keys($normalized['changed']),
            ]);

            return ['post' => $this->get($id), 'removed' => $normalized['removed']];
        });
    }

    /** @return array<string, mixed> Arbeitsstand plus Ablauf-Felder */
    public function get(int $id): array
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw $this->notFound($id);
        }

        return $this->withWorkflowFields($this->snapshot($id), $post);
    }

    /** @return array<string, mixed> */
    public function getBySlug(string $slug): array
    {
        $post = $this->posts->findBySlug($slug);
        if ($post === null) {
            throw new DomainError('not_found', sprintf('Den Artikel mit dem Slug „%s“ gibt es nicht.', $slug), ['slug' => $slug]);
        }

        return $this->withWorkflowFields($this->snapshot((int) $post['id']), $post);
    }

    /**
     * @param list<string> $statuses leer = alle
     * @return list<array<string, mixed>>
     */
    public function list(array $statuses = [], int $limit = 50, int $offset = 0, ?string $category = null, ?string $tag = null, ?int $year = null): array
    {
        if ($category !== null && $this->categories->findBySlug($category) === null) {
            $validSlugs = array_map(static fn (array $row): string => (string) $row['slug'], $this->categories->all(true));
            throw new DomainError(
                'not_found',
                sprintf('Die Kategorie „%s“ gibt es nicht. Gültig: %s.', $category, implode(', ', $validSlugs)),
                ['field' => 'category', 'valid' => $validSlugs]
            );
        }

        return $this->posts->list($statuses, $limit, $offset, $category, $tag, $year);
    }

    /**
     * Sucht in Titel, Untertitel, Auszug und Text; Titeltreffer zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $term = trim($query);
        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            throw $this->invalid('query', sprintf('braucht mindestens %d Zeichen', self::SEARCH_MIN_LENGTH));
        }

        return $this->posts->search($term, min(max($limit, 1), self::SEARCH_MAX_LIMIT));
    }

    /**
     * Das vollständige Bild des Arbeitsstands, gebaut aus den Tabellen.
     *
     * @return array<string, mixed>
     */
    public function snapshot(int $id): array
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw $this->notFound($id);
        }

        $coverUid = $post['cover_media_id'] === null ? null : $this->posts->mediaUid((int) $post['cover_media_id']);

        return [
            'id' => (int) $post['id'],
            'revision' => (int) $post['revision'],
            'slug' => $post['slug'],
            'title' => $post['title'],
            'subtitle' => $post['subtitle'],
            'kicker' => $post['kicker'],
            'excerpt' => $post['excerpt'],
            'content_html' => $post['content_html'],
            'tags' => $post['tags'],
            'layout' => $post['layout'],
            'accent' => $post['accent'],
            'cover_media_uid' => $coverUid,
            'seo_title' => $post['seo_title'],
            'seo_description' => $post['seo_description'],
            'origin' => $post['origin'],
            'queue_item_id' => $post['queue_item_id'] === null ? null : (int) $post['queue_item_id'],
            'depth' => $post['depth'],
            'ai_assisted' => $post['ai_assisted'],
            'word_count' => (int) $post['word_count'],
            'editor_notes' => $post['editor_notes'],
            'categories' => $this->posts->categories($id),
            'sources' => $this->posts->sources($id),
        ];
    }

    /**
     * Der Arbeitsstand in der Form eines Live-Snapshots (mit `published_at`, `updated_at`), damit die Vorschau ihn wie
     * einen veröffentlichten Artikel darstellt. Ohne Veröffentlichung steht die Änderungszeit als Datum.
     *
     * @return array<string, mixed>
     */
    public function previewSnapshot(int $id): array
    {
        $post = $this->posts->find($id);
        if ($post === null) {
            throw $this->notFound($id);
        }

        return $this->snapshot($id) + [
            'published_at' => $post['published_at'] ?? $post['updated_at'],
            'updated_at' => $post['updated_at'],
        ];
    }

    /** @return list<array{revision: int, actor: string, created_at: string}> */
    public function revisions(int $id): array
    {
        if ($this->posts->find($id) === null) {
            throw $this->notFound($id);
        }

        return $this->posts->revisions($id);
    }

    /** @return array<string, mixed> der Snapshot der Revision */
    public function revision(int $id, int $revision): array
    {
        $snapshot = $this->posts->revision($id, $revision);
        if ($snapshot === null) {
            throw new DomainError(
                'not_found',
                sprintf('Die Revision %d des Artikels %d gibt es nicht.', $revision, $id),
                ['id' => $id, 'revision' => $revision]
            );
        }

        return $snapshot;
    }

    /** Prüft den Arbeitsstand gegen die Formvorgaben; schreibt nichts. */
    public function validate(int $id): FormCheckResult
    {
        $snapshot = $this->snapshot($id);
        $mediaByUid = $this->media->findManyByUid(FormCheck::mediaUids($snapshot));

        return $this->formCheck->check($snapshot, $mediaByUid, $this->posts->liveTitles($id), (array) $this->config['form_rules']);
    }

    /** @return array<string, mixed> der Artikel nach dem Übergang */
    public function submit(int $id, Actor $actor): array
    {
        return Transaction::join($this->pdo, function () use ($id, $actor): array {
            $post = $this->lockedPost($id);
            $hasUnpublishedChanges = $post['published_revision'] !== null && (int) $post['revision'] > (int) $post['published_revision'];
            $isAllowed = $post['status'] === 'draft' || ($post['status'] === 'published' && $hasUnpublishedChanges);
            if (!$isAllowed) {
                throw $this->invalidTransition($post, 'submit');
            }

            $this->assertFormOk($id);
            $this->posts->setStatus($id, ['status' => 'review']);
            $this->queueAfter($post, 'markReview');
            $this->audit->record($actor, 'post.submit', 'post:' . $id, ['revision' => (int) $post['revision']]);

            return $this->get($id);
        });
    }

    /** @return array<string, mixed> */
    public function approve(int $id, Actor $actor): array
    {
        $this->requireOwner($actor, 'freigeben');

        return Transaction::join($this->pdo, function () use ($id, $actor): array {
            $post = $this->lockedPost($id);
            if ($post['status'] !== 'review') {
                throw $this->invalidTransition($post, 'approve');
            }

            $this->assertFormOk($id);
            $this->posts->setStatus($id, [
                'status' => 'published',
                'published_revision' => (int) $post['revision'],
                'touch_published_at' => true,
                'review_note' => null,
            ]);
            $this->queueAfter($post, 'markDone');
            $this->audit->record($actor, 'post.approve', 'post:' . $id, ['revision' => (int) $post['revision']]);

            return $this->get($id);
        });
    }

    /** @return array<string, mixed> */
    public function returnToAgent(int $id, string $note, Actor $actor): array
    {
        $this->requireOwner($actor, 'zurückgeben');
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > self::REVIEW_NOTE_MAX_LENGTH) {
            throw $this->invalid('note', sprintf('muss 1 bis %d Zeichen lang sein und sagen, was zu ändern ist', self::REVIEW_NOTE_MAX_LENGTH));
        }

        return Transaction::join($this->pdo, function () use ($id, $note, $actor): array {
            $post = $this->lockedPost($id);
            if ($post['status'] !== 'review') {
                throw $this->invalidTransition($post, 'return');
            }

            $this->posts->setStatus($id, ['status' => 'draft', 'review_note' => $note]);
            $this->audit->record($actor, 'post.return', 'post:' . $id, ['revision' => (int) $post['revision']]);

            return $this->get($id);
        });
    }

    /** @return array<string, mixed> */
    public function unpublish(int $id, Actor $actor): array
    {
        $this->requireOwner($actor, 'zurückziehen');

        return Transaction::join($this->pdo, function () use ($id, $actor): array {
            $post = $this->lockedPost($id);
            $isAllowed = $post['status'] === 'published'
                || ($post['status'] === 'review' && $post['published_revision'] !== null);
            if (!$isAllowed) {
                throw $this->invalidTransition($post, 'unpublish');
            }

            // published_revision bleibt als Nachweis, was zuletzt online war; livePosts() schließt archivierte aus.
            $this->posts->setStatus($id, ['status' => 'archived']);
            $this->audit->record($actor, 'post.unpublish', 'post:' . $id, ['published_revision' => (int) $post['published_revision']]);

            return $this->get($id);
        });
    }

    /**
     * Nimmt einen eingereichten Stand aus der Prüfung zurück. Ein nie veröffentlichter Artikel wird wieder Entwurf,
     * ein veröffentlichter bleibt mit seinem Live-Stand online und behält den neuen Arbeitsstand.
     *
     * @return array<string, mixed>
     */
    public function withdraw(int $id, Actor $actor): array
    {
        return Transaction::join($this->pdo, function () use ($id, $actor): array {
            $post = $this->lockedPost($id);
            if ($post['status'] !== 'review') {
                throw $this->invalidTransition($post, 'withdraw');
            }

            $target = $post['published_revision'] === null ? 'draft' : 'published';
            $this->posts->setStatus($id, ['status' => $target]);
            $this->queueAfter($post, 'backToClaimed');
            $this->audit->record($actor, 'post.withdraw', 'post:' . $id, ['revision' => (int) $post['revision'], 'status' => $target]);

            return $this->get($id);
        });
    }

    /**
     * Stellt den Inhalt einer früheren Revision als neue Revision wieder her; der Slug bleibt.
     *
     * @return array{post: array<string, mixed>, removed: list<string>}
     */
    public function restoreRevision(int $id, int $revision, Actor $actor): array
    {
        $snapshot = $this->revision($id, $revision);

        $patch = [];
        foreach (self::RESTORABLE_FIELDS as $field) {
            if (array_key_exists($field, $snapshot)) {
                $patch[$field] = $snapshot[$field];
            }
        }
        if (array_key_exists('cover_media_uid', $snapshot)) {
            $patch['cover_media_uid'] = $snapshot['cover_media_uid'];
        }
        if (array_key_exists('categories', $snapshot)) {
            $patch['categories'] = array_map(
                static fn (array $category): array => ['slug' => $category['slug'], 'primary' => $category['primary']],
                $snapshot['categories']
            );
        }

        return Transaction::join($this->pdo, function () use ($id, $revision, $patch, $actor): array {
            $result = $this->update($id, $patch, $actor);
            $this->audit->record($actor, 'post.restore', 'post:' . $id, [
                'from_revision' => $revision,
                'revision' => $result['post']['revision'],
            ]);

            return $result;
        });
    }

    /**
     * Löscht einen nie veröffentlichten Artikel samt Revisionen, Quellen und Kategorie-Zuordnungen.
     * Der Inhaber darf jeden solchen Artikel verwerfen, Agenten nur Entwürfe.
     */
    public function discard(int $id, Actor $actor): void
    {
        Transaction::join($this->pdo, function () use ($id, $actor): void {
            $post = $this->lockedPost($id);
            if ($post['published_revision'] !== null) {
                throw new DomainError(
                    'invalid_transition',
                    'Aus Status ' . $post['status'] . ' geht discard nicht: Der Artikel war schon veröffentlicht, und Veröffentlichtes wird zurückgezogen (unpublish), nicht gelöscht.',
                    ['id' => $id, 'status' => $post['status'], 'action' => 'discard']
                );
            }
            if (!$actor->isOwner() && $post['status'] !== 'draft') {
                throw new DomainError(
                    'forbidden',
                    'Agenten löschen nur Entwürfe. Eingereichtes erst mit withdraw_post zurückziehen.',
                    ['id' => $id, 'status' => $post['status']]
                );
            }

            $this->posts->delete($id);
            $this->queueAfter($post, 'reopen');
            $this->audit->record($actor, 'post.discard', 'post:' . $id, ['title' => $post['title'], 'slug' => $post['slug']]);
        });
    }

    /**
     * Die Artikel der Website: Snapshots der freigegebenen Revisionen.
     *
     * @return list<array<string, mixed>>
     */
    public function live(): array
    {
        return $this->posts->livePosts();
    }

    /** @return array<string, mixed> Zeile gesperrt bis zum Transaktionsende */
    private function lockedPost(int $id): array
    {
        $post = $this->posts->findForUpdate($id);
        if ($post === null) {
            throw $this->notFound($id);
        }

        return $post;
    }

    /**
     * Zieht den verknüpften Queue-Auftrag in derselben Transaktion nach.
     *
     * @param array<string, mixed> $post
     * @param 'markReview'|'markDone'|'reopen'|'backToClaimed' $transition
     */
    private function queueAfter(array $post, string $transition): void
    {
        if ($post['queue_item_id'] !== null) {
            $this->queue->{$transition}((int) $post['queue_item_id']);
        }
    }

    /**
     * `queue_item_id` gehört nicht zu den Artikel-Feldern: Nur das Anlegen kennt es, und es setzt dann `origin = queue`.
     *
     * @param array<string, mixed> $input wird um den Schlüssel bereinigt
     */
    private function extractQueueItemId(array &$input): ?int
    {
        if (!array_key_exists('queue_item_id', $input)) {
            return null;
        }
        $value = $input['queue_item_id'];
        unset($input['queue_item_id']);
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 1) {
            throw $this->invalid('queue_item_id', 'muss die Nummer eines Auftrags sein');
        }
        if (array_key_exists('origin', $input)) {
            throw $this->invalid('origin', 'darf zusammen mit queue_item_id nicht gesetzt werden — die Queue bestimmt ihn');
        }

        return $value;
    }

    private function assertFormOk(int $id): void
    {
        $result = $this->validate($id);
        if (!$result->ok()) {
            throw new DomainError(
                'form_errors',
                sprintf('Die Formprüfung hat %d Fehler. Behebe sie und reiche erneut ein.', count($result->errors)),
                ['result' => $result->toArray()]
            );
        }
    }

    private function requireOwner(Actor $actor, string $action): void
    {
        if (!$actor->isOwner()) {
            throw new DomainError('owner_only', sprintf('Nur der Inhaber darf einen Artikel %s.', $action));
        }
    }

    /** @param array<string, mixed> $post */
    private function invalidTransition(array $post, string $action): DomainError
    {
        return new DomainError(
            'invalid_transition',
            sprintf('Aus Status %s geht %s nicht.', $post['status'], $action),
            ['id' => (int) $post['id'], 'status' => $post['status'], 'action' => $action]
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function withWorkflowFields(array $snapshot, array $post): array
    {
        return $snapshot + [
            'status' => $post['status'],
            'review_note' => $post['review_note'],
            'published_revision' => $post['published_revision'] === null ? null : (int) $post['published_revision'],
            'published_at' => $post['published_at'],
        ];
    }

    /**
     * Prüft alles, bevor die erste Zeile geschrieben wird — ein Fehler lässt die DB unberührt.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $current null beim Anlegen
     * @return array{columns: array<string, mixed>, changed: array<string, true>, categories: list<array{category_id: int, is_primary: bool}>|null, sources: list<array<string, mixed>>|null, removed: list<string>}
     */
    private function normalize(array $input, ?int $id, Actor $actor, ?array $current = null): array
    {
        $columns = [];
        $changed = [];
        $categories = null;
        $sources = null;
        $removed = [];

        foreach ($input as $field => $value) {
            $field = (string) $field;
            if (!in_array($field, self::FIELDS, true)) {
                throw $this->invalid($field, 'ist kein Artikel-Feld. Gültig: ' . implode(', ', self::FIELDS));
            }
            $changed[$field] = true;

            switch ($field) {
                case 'title':
                    $columns['title'] = $this->normalizeTitle($value);
                    break;
                case 'subtitle':
                case 'kicker':
                case 'excerpt':
                case 'seo_title':
                case 'seo_description':
                    $columns[$field] = $this->normalizeOptionalText($field, $value, self::OPTIONAL_TEXT_LENGTHS[$field]);
                    break;
                case 'slug':
                    $slug = $this->normalizeExplicitSlug($value, $id, $actor, $current);
                    if ($slug !== null) {
                        $columns['slug'] = $slug;
                    }
                    break;
                case 'content_html':
                    if (!is_string($value)) {
                        throw $this->invalid('content_html', 'muss HTML als Text sein');
                    }
                    $sanitized = $this->sanitizer->sanitize($value);
                    $columns['content_html'] = $sanitized->html;
                    $columns['word_count'] = Text::wordCount($sanitized->html);
                    $removed = $sanitized->removed;
                    break;
                case 'tags':
                    $columns['tags'] = $this->normalizeTags($value);
                    break;
                case 'layout':
                    $columns['layout'] = $this->normalizeEnum('layout', $value, self::LAYOUTS);
                    break;
                case 'accent':
                    $columns['accent'] = $this->normalizeAccent($value);
                    break;
                case 'cover_media_uid':
                    $columns['cover_media_id'] = $this->normalizeCover($value);
                    break;
                case 'origin':
                    $columns['origin'] = $this->normalizeOrigin($value);
                    break;
                case 'depth':
                    $columns['depth'] = $this->normalizeEnum('depth', $value, self::DEPTHS);
                    break;
                case 'editor_notes':
                    $columns['editor_notes'] = $this->normalizeEditorNotes($value);
                    break;
                case 'ai_assisted':
                    if (!is_bool($value)) {
                        throw $this->invalid('ai_assisted', 'muss true oder false sein');
                    }
                    $columns['ai_assisted'] = $value;
                    break;
                case 'categories':
                    $categories = $this->normalizeCategories($value);
                    break;
                case 'sources':
                    $sources = $this->normalizeSources($value);
                    break;
            }
        }

        return ['columns' => $columns, 'changed' => $changed, 'categories' => $categories, 'sources' => $sources, 'removed' => $removed];
    }

    private function normalizeTitle(mixed $value): string
    {
        $title = is_string($value) ? trim($value) : '';
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw $this->invalid('title', sprintf('muss 1 bis %d Zeichen lang sein', self::TITLE_MAX_LENGTH));
        }

        return $title;
    }

    private function normalizeOptionalText(string $field, mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw $this->invalid($field, sprintf('muss ein Text mit höchstens %d Zeichen oder null sein', $maxLength));
        }
        $text = trim($value);

        return $text === '' ? null : $text;
    }

    /**
     * @param array<string, mixed>|null $current
     * @return string|null null = Slug bleibt bzw. wird beim Anlegen aus dem Titel erzeugt
     */
    private function normalizeExplicitSlug(mixed $value, ?int $id, Actor $actor, ?array $current): ?string
    {
        if ($value === null && $current === null) {
            return null;
        }
        if (!is_string($value) || strlen($value) > self::SLUG_MAX_LENGTH || preg_match(self::SLUG_PATTERN, $value) !== 1) {
            throw $this->invalid('slug', sprintf('muss aus Kleinbuchstaben, Ziffern und einzelnen Bindestrichen bestehen (höchstens %d Zeichen), z. B. „ueber-groesse-mass“', self::SLUG_MAX_LENGTH));
        }
        if ($current !== null && $value === $current['slug']) {
            return null;
        }
        if ($current !== null && $current['published_revision'] !== null && !$actor->isOwner()) {
            throw new DomainError('slug_locked', 'Der Slug eines veröffentlichten Artikels ist seine öffentliche Adresse; nur der Inhaber darf ihn ändern.', ['field' => 'slug']);
        }
        if ($this->posts->slugExists($value, $id)) {
            throw new DomainError('slug_taken', sprintf('Der Slug „%s“ ist schon vergeben. Wähle einen anderen.', $value), ['field' => 'slug']);
        }

        return $value;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while ($this->posts->slugExists($slug, null)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /** @return list<string> */
    private function normalizeTags(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid('tags', 'muss eine Liste von Zeichenketten sein');
        }

        $tags = [];
        $seen = [];
        foreach ($value as $tag) {
            $trimmed = is_string($tag) ? trim($tag) : '';
            if ($trimmed === '' || mb_strlen($trimmed) > self::TAG_MAX_LENGTH) {
                throw $this->invalid('tags', sprintf('darf nur Einträge mit 1 bis %d Zeichen enthalten', self::TAG_MAX_LENGTH));
            }
            $key = mb_strtolower($trimmed);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $tags[] = $trimmed;
            }
        }
        if (count($tags) > self::TAGS_MAX) {
            throw $this->invalid('tags', sprintf('darf höchstens %d Einträge haben', self::TAGS_MAX));
        }

        return $tags;
    }

    /** @param list<string> $allowed */
    private function normalizeEnum(string $field, mixed $value, array $allowed): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw $this->invalid($field, 'muss einer dieser Werte sein: ' . implode(', ', $allowed));
        }

        return $value;
    }

    private function normalizeOrigin(mixed $value): string
    {
        if ($value === 'queue') {
            throw $this->invalid('origin', 'darf nicht „queue“ sein — diesen Wert setzt nur die Queue selbst. Erlaubt: ' . implode(', ', self::ORIGINS));
        }

        return $this->normalizeEnum('origin', $value, self::ORIGINS);
    }

    private function normalizeAccent(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || preg_match(self::ACCENT_PATTERN, $value) !== 1) {
            throw $this->invalid('accent', 'muss eine Farbe wie „#1a73e8“ (sechsstelliger Hex-Wert) oder null sein');
        }

        return $value;
    }

    private function normalizeCover(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $medium = is_string($value) ? $this->media->findByUid($value) : null;
        if ($medium === null) {
            throw $this->invalid('cover_media_uid', 'verweist auf ein Medium, das es nicht gibt');
        }
        if ($medium['kind'] !== 'image') {
            throw $this->invalid('cover_media_uid', 'muss ein Bild sein, kein ' . $medium['kind']);
        }

        return (int) $medium['id'];
    }

    private function normalizeEditorNotes(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > self::EDITOR_NOTES_MAX_LENGTH) {
            throw $this->invalid('editor_notes', sprintf('muss ein Text mit höchstens %d Zeichen oder null sein', self::EDITOR_NOTES_MAX_LENGTH));
        }

        return $value;
    }

    /** @return list<array{category_id: int, is_primary: bool}> */
    private function normalizeCategories(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid('categories', 'muss eine Liste von Objekten {slug, primary?} sein');
        }

        $rows = [];
        $primaryCount = 0;
        foreach ($value as $entry) {
            $slug = is_array($entry) ? ($entry['slug'] ?? null) : null;
            $isPrimary = is_array($entry) ? ($entry['primary'] ?? false) : false;
            if (!is_string($slug) || !is_bool($isPrimary)) {
                throw $this->invalid('categories', 'darf nur Objekte der Form {slug: Text, primary: true|false} enthalten');
            }
            $category = $this->categories->findBySlug($slug);
            if ($category === null || !$category['active']) {
                throw new DomainError(
                    'unknown_category',
                    sprintf('Die Kategorie „%s“ gibt es nicht oder sie ist nicht aktiv. Nimm einen Slug aus der Kategorienliste.', $slug),
                    ['field' => 'categories', 'slug' => $slug]
                );
            }
            if (isset($rows[$category['id']])) {
                throw $this->invalid('categories', sprintf('nennt die Kategorie „%s“ mehrfach', $slug));
            }
            $primaryCount += $isPrimary ? 1 : 0;
            $rows[$category['id']] = ['category_id' => (int) $category['id'], 'is_primary' => $isPrimary];
        }
        if ($primaryCount > 1) {
            throw $this->invalid('categories', 'darf höchstens eine Hauptkategorie (primary: true) enthalten');
        }

        $rows = array_values($rows);
        if ($rows !== [] && $primaryCount === 0) {
            $rows[0]['is_primary'] = true;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function normalizeSources(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid('sources', 'muss eine Liste von Quellen-Objekten sein');
        }

        $rows = [];
        $seenRefs = [];
        foreach ($value as $index => $entry) {
            if (!is_array($entry)) {
                throw $this->invalid('sources', sprintf('Eintrag %d muss ein Objekt sein', $index + 1));
            }
            $row = $this->normalizeSource($entry, $index + 1);
            if (isset($seenRefs[$row['ref']])) {
                throw new DomainError(
                    'duplicate_ref',
                    sprintf('Der Beleg-Schlüssel „%s“ kommt mehrfach vor. Jede Quelle braucht einen eigenen ref.', $row['ref']),
                    ['field' => 'sources', 'ref' => $row['ref']]
                );
            }
            $seenRefs[$row['ref']] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function normalizeSource(array $entry, int $number): array
    {
        $ref = $entry['ref'] ?? null;
        if (!is_string($ref) || preg_match(self::REF_PATTERN, $ref) !== 1) {
            throw $this->invalid('sources', sprintf('Quelle %d: ref muss 1 bis 60 Zeichen aus Buchstaben, Ziffern, „.“, „_“, „-“ sein und mit Buchstabe oder Ziffer beginnen', $number));
        }
        $kind = $entry['kind'] ?? null;
        if (!is_string($kind) || !in_array($kind, self::SOURCE_KINDS, true)) {
            throw $this->invalid('sources', sprintf('Quelle %d: kind muss einer dieser Werte sein: %s', $number, implode(', ', self::SOURCE_KINDS)));
        }
        $title = is_string($entry['title'] ?? null) ? trim($entry['title']) : '';
        if ($title === '' || mb_strlen($title) > self::SOURCE_TITLE_MAX_LENGTH) {
            throw $this->invalid('sources', sprintf('Quelle %d: title muss 1 bis %d Zeichen lang sein', $number, self::SOURCE_TITLE_MAX_LENGTH));
        }

        $url = $this->sourceText($entry, 'url', $number, self::SOURCE_URL_MAX_LENGTH);
        if ($url !== null && preg_match('#^https?://#i', $url) !== 1) {
            throw $this->invalid('sources', sprintf('Quelle %d: url muss mit http:// oder https:// beginnen', $number));
        }
        $published = $this->sourceText($entry, 'published', $number, 10);
        if ($published !== null && preg_match(self::PUBLISHED_PATTERN, $published) !== 1) {
            throw $this->invalid('sources', sprintf('Quelle %d: published muss JJJJ, JJJJ-MM oder JJJJ-MM-TT sein', $number));
        }

        return [
            'ref' => $ref,
            'kind' => $kind,
            'title' => $title,
            'url' => $url,
            'publisher' => $this->sourceText($entry, 'publisher', $number, self::SOURCE_TEXT_MAX_LENGTH),
            'author' => $this->sourceText($entry, 'author', $number, self::SOURCE_TEXT_MAX_LENGTH),
            'published' => $published,
            'accessed' => $this->sourceDate($entry, $number),
            'note' => $this->sourceText($entry, 'note', $number, self::SOURCE_NOTE_MAX_LENGTH),
        ];
    }

    /** @param array<string, mixed> $entry */
    private function sourceText(array $entry, string $key, int $number, int $maxLength): ?string
    {
        $value = $entry[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > $maxLength) {
            throw $this->invalid('sources', sprintf('Quelle %d: %s muss ein Text mit höchstens %d Zeichen sein', $number, $key, $maxLength));
        }
        $text = trim($value);

        return $text === '' ? null : $text;
    }

    /** @param array<string, mixed> $entry */
    private function sourceDate(array $entry, int $number): ?string
    {
        $value = $entry['accessed'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        $isDate = is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        if (!$isDate) {
            throw $this->invalid('sources', sprintf('Quelle %d: accessed muss ein gültiges Datum JJJJ-MM-TT sein', $number));
        }

        return $value;
    }

    private function invalid(string $field, string $reason): DomainError
    {
        return new DomainError('invalid_value', sprintf('Das Feld „%s“ %s.', $field, $reason), ['field' => $field]);
    }

    private function notFound(int $id): DomainError
    {
        return new DomainError('not_found', sprintf('Den Artikel %d gibt es nicht.', $id), ['id' => $id]);
    }
}
