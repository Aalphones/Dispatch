<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

final class QueueService
{
    private const TITLE_MAX_LENGTH = 200;
    private const BRIEF_MAX_LENGTH = 10000;
    private const LINK_MAX_LENGTH = 2000;
    private const LINKS_MAX = 20;
    private const NOTE_MAX_LENGTH = 2000;
    private const MEDIA_NOTE_MAX_LENGTH = 500;
    private const PRIORITY_MIN = 1;
    private const PRIORITY_MAX = 3;
    private const PRIORITY_DEFAULT = 2;
    private const CLAIM_CANDIDATES = 5;

    private const FIELDS = ['title', 'brief', 'links', 'priority', 'depth', 'category', 'media'];
    private const DEPTHS = ['standard', 'deep'];
    private const STATUSES = ['open', 'claimed', 'review', 'done', 'failed', 'cancelled'];
    private const RELEASE_STATUSES = ['open', 'failed'];
    private const OWN_MATERIAL_LICENSE = 'Eigenes Material';

    private QueueRepository $queue;
    private CategoryRepository $categories;
    private MediaRepository $media;
    private MediaService $mediaService;
    private SettingService $settings;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, array $config)
    {
        $this->queue = new QueueRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
        $this->media = new MediaRepository($pdo);
        $this->mediaService = new MediaService($pdo, $config);
        $this->settings = new SettingService($pdo, $config);
        $this->audit = new AuditRepository($pdo);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function add(array $input, Actor $actor): array
    {
        $normalized = $this->normalizeNew($input);

        return Transaction::join($this->pdo, function () use ($normalized, $actor): array {
            $itemId = $this->queue->insert($normalized['columns'] + [
                'status' => 'open',
                'created_by' => $actor->name,
            ]);
            foreach ($normalized['media'] as $attachment) {
                $this->queue->attachMedia($itemId, $attachment['id'], $attachment['note'], $attachment['own_material']);
                if ($attachment['own_material']) {
                    $this->markAsOwnMaterial($attachment['uid'], $actor);
                }
            }
            $this->audit->record($actor, 'queue.add', 'queue:' . $itemId, [
                'title' => $normalized['columns']['title'],
                'priority' => $normalized['columns']['priority'],
            ]);

            return $this->get($itemId);
        });
    }

    /** @return array<string, mixed> Auftrag mit `effective_status` und Medien */
    public function get(int $id): array
    {
        $item = $this->queue->find($id);
        if ($item === null) {
            throw $this->notFound($id);
        }

        return $item + ['media' => $this->queue->media($id)];
    }

    /**
     * @param list<string> $statuses leer = alle; gefiltert wird nach dem Status, den der Leser sieht
     * @return list<array<string, mixed>>
     */
    public function list(array $statuses = ['open', 'claimed', 'review'], int $limit = 50, int $offset = 0): array
    {
        foreach ($statuses as $status) {
            if (!in_array($status, self::STATUSES, true)) {
                throw new DomainError('invalid_value', sprintf('Unbekannter Status „%s“. Gültig: %s.', $status, implode(', ', self::STATUSES)), ['field' => 'status']);
            }
        }

        return $this->queue->list($statuses, $limit, $offset);
    }

    /**
     * Mit `id` genau dieser Auftrag, ohne `id` der wichtigste und älteste offene.
     *
     * @return array<string, mixed>
     */
    public function claim(?int $id, Actor $actor): array
    {
        $minutes = (int) $this->settings->get('queue_claim_minutes');

        if ($id !== null) {
            if (!$this->tryClaim($id, $actor, $minutes)) {
                throw $this->claimFailure($id);
            }

            return $this->get($id);
        }

        foreach ($this->queue->claimCandidates(self::CLAIM_CANDIDATES) as $candidateId) {
            if ($this->tryClaim($candidateId, $actor, $minutes)) {
                return $this->get($candidateId);
            }
        }

        throw new DomainError('queue_empty', 'Es ist gerade kein Auftrag offen.');
    }

    /** @return array<string, mixed> */
    public function release(int $id, string $status, ?string $note, Actor $actor): array
    {
        if (!in_array($status, self::RELEASE_STATUSES, true)) {
            throw new DomainError('invalid_value', sprintf('Beim Freigeben ist nur %s erlaubt.', implode(' oder ', self::RELEASE_STATUSES)), ['field' => 'status']);
        }
        $note = $note === null ? null : trim($note);
        if ($note === '') {
            $note = null;
        }
        if ($status === 'failed' && $note === null) {
            throw new DomainError('invalid_value', 'Zum Abbrechen als „failed“ gehört eine Begründung (note, 1 bis 2000 Zeichen).', ['field' => 'note']);
        }
        if ($note !== null && mb_strlen($note) > self::NOTE_MAX_LENGTH) {
            throw new DomainError('invalid_value', sprintf('Die Begründung darf höchstens %d Zeichen lang sein.', self::NOTE_MAX_LENGTH), ['field' => 'note']);
        }

        return Transaction::join($this->pdo, function () use ($id, $status, $note, $actor): array {
            $item = $this->assertClaimant($id, $actor);
            if ($item['post_id'] !== null) {
                throw new DomainError('has_post', 'Zu diesem Auftrag gibt es schon einen Artikel. Verwirf ihn erst, dann lässt sich der Auftrag freigeben.', ['id' => $id, 'post_id' => $item['post_id']]);
            }

            $this->queue->update($id, [
                'status' => $status,
                'claimed_by' => null,
                'claim_expires' => null,
                'result_note' => $note,
            ]);
            $this->audit->record($actor, 'queue.release', 'queue:' . $id, ['status' => $status]);

            return $this->get($id);
        });
    }

    /** @return array<string, mixed> */
    public function cancel(int $id, Actor $actor): array
    {
        if (!$actor->isOwner()) {
            throw new DomainError('owner_only', 'Nur der Inhaber darf einen Auftrag zurückziehen.');
        }

        return Transaction::join($this->pdo, function () use ($id, $actor): array {
            $item = $this->lockedItem($id);
            if (!in_array($item['status'], ['open', 'claimed'], true)) {
                throw new DomainError('invalid_transition', sprintf('Aus Status %s geht cancel nicht.', $item['status']), ['id' => $id, 'status' => $item['status'], 'action' => 'cancel']);
            }
            if ($item['post_id'] !== null) {
                throw new DomainError('has_post', 'Zu diesem Auftrag gibt es schon einen Artikel. Verwirf ihn erst, dann lässt sich der Auftrag zurückziehen.', ['id' => $id, 'post_id' => $item['post_id']]);
            }

            $this->queue->update($id, ['status' => 'cancelled', 'claimed_by' => null, 'claim_expires' => null]);
            $this->audit->record($actor, 'queue.cancel', 'queue:' . $id);

            return $this->get($id);
        });
    }

    /**
     * Prüft den Rohzustand, nicht die Uhr: Wer seine abgelaufene Reservierung noch hält, darf arbeiten;
     * hat ein anderer neu reserviert, steht dessen Name in `claimed_by`.
     * Sperrt die Zeile — nur in einer Transaktion sinnvoll.
     *
     * @return array<string, mixed>
     */
    public function assertClaimant(int $id, Actor $actor): array
    {
        $item = $this->lockedItem($id);
        if ($item['status'] !== 'claimed') {
            throw new DomainError('not_claimed', sprintf('Der Auftrag %d ist nicht reserviert (Status %s). Reserviere ihn zuerst.', $id, $item['status']), ['id' => $id, 'status' => $item['status']]);
        }
        if ($item['claimed_by'] !== $actor->name && !$actor->isOwner()) {
            throw new DomainError('not_claimant', sprintf('Der Auftrag %d ist von jemand anderem reserviert.', $id), ['id' => $id]);
        }

        return $item;
    }

    public function linkPost(int $id, int $postId): void
    {
        $item = $this->lockedItem($id);
        if ($item['post_id'] !== null) {
            throw new DomainError('already_linked', sprintf('Zum Auftrag %d gibt es schon einen Artikel.', $id), ['id' => $id, 'post_id' => $item['post_id']]);
        }

        $this->queue->update($id, ['post_id' => $postId]);
    }

    /** Wirkt nur aus `claimed` oder `review`; ein erledigter Auftrag bleibt, wenn ein Artikel später erneut eingereicht wird. */
    public function markReview(int $id): void
    {
        $item = $this->lockedItem($id);
        if (in_array($item['status'], ['claimed', 'review'], true)) {
            $this->queue->update($id, ['status' => 'review']);
        }
    }

    public function markDone(int $id): void
    {
        $item = $this->lockedItem($id);
        if ($item['status'] === 'review') {
            $this->queue->update($id, ['status' => 'done']);
        }
    }

    /** Der Artikel ist weg: der Auftrag steht wieder zur Vergabe. */
    public function reopen(int $id): void
    {
        $item = $this->lockedItem($id);
        if (in_array($item['status'], ['claimed', 'review'], true)) {
            $this->queue->update($id, ['status' => 'open', 'post_id' => null, 'claimed_by' => null, 'claim_expires' => null]);
        }
    }

    private function tryClaim(int $id, Actor $actor, int $minutes): bool
    {
        return Transaction::join($this->pdo, function () use ($id, $actor, $minutes): bool {
            if (!$this->queue->tryClaim($id, $actor->name, $minutes)) {
                return false;
            }
            $this->audit->record($actor, 'queue.claim', 'queue:' . $id, ['minutes' => $minutes]);

            return true;
        });
    }

    private function claimFailure(int $id): DomainError
    {
        if ($this->queue->find($id) === null) {
            return $this->notFound($id);
        }

        return new DomainError('already_claimed', 'Auftrag ist reserviert oder nicht mehr offen.', ['id' => $id]);
    }

    /** @return array<string, mixed> Zeile gesperrt bis zum Transaktionsende; `status` ist der Rohzustand */
    private function lockedItem(int $id): array
    {
        $item = $this->queue->findForUpdate($id);
        if ($item === null) {
            throw $this->notFound($id);
        }

        return $item;
    }

    private function markAsOwnMaterial(string $uid, Actor $actor): void
    {
        $medium = $this->mediaService->get($uid);
        $fields = [];
        if ($medium['credit'] === null) {
            $authorName = trim((string) $this->settings->get('author_name'));
            if ($authorName !== '') {
                $fields['credit'] = $authorName;
            }
        }
        if ($medium['license'] === null) {
            $fields['license'] = self::OWN_MATERIAL_LICENSE;
        }
        if ($fields !== []) {
            $this->mediaService->updateMeta($uid, $fields, $actor);
        }
    }

    /**
     * Prüft alles, bevor die erste Zeile geschrieben wird.
     *
     * @param array<string, mixed> $input
     * @return array{columns: array<string, mixed>, media: list<array{id: int, uid: string, note: string|null, own_material: bool}>}
     */
    private function normalizeNew(array $input): array
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::FIELDS, true)) {
                throw $this->invalid((string) $field, 'ist kein Auftrags-Feld. Gültig: ' . implode(', ', self::FIELDS));
            }
        }
        if (!array_key_exists('title', $input)) {
            throw $this->invalid('title', 'ist Pflicht');
        }

        return [
            'columns' => [
                'title' => $this->normalizeTitle($input['title']),
                'brief' => $this->normalizeBrief($input['brief'] ?? null),
                'links' => $this->normalizeLinks($input['links'] ?? []),
                'priority' => $this->normalizePriority($input['priority'] ?? self::PRIORITY_DEFAULT),
                'depth' => $this->normalizeDepth($input['depth'] ?? 'standard'),
                'category_id' => $this->normalizeCategory($input['category'] ?? null),
            ],
            'media' => $this->normalizeMedia($input['media'] ?? []),
        ];
    }

    private function normalizeTitle(mixed $value): string
    {
        $title = is_string($value) ? trim($value) : '';
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw $this->invalid('title', sprintf('muss 1 bis %d Zeichen lang sein', self::TITLE_MAX_LENGTH));
        }

        return $title;
    }

    private function normalizeBrief(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen(trim($value)) > self::BRIEF_MAX_LENGTH) {
            throw $this->invalid('brief', sprintf('muss ein Text mit höchstens %d Zeichen oder null sein', self::BRIEF_MAX_LENGTH));
        }
        $brief = trim($value);

        return $brief === '' ? null : $brief;
    }

    /** @return list<string> */
    private function normalizeLinks(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::LINKS_MAX) {
            throw $this->invalid('links', sprintf('muss eine Liste mit höchstens %d Web-Adressen sein', self::LINKS_MAX));
        }

        $links = [];
        foreach ($value as $link) {
            $url = is_string($link) ? trim($link) : '';
            if (strlen($url) > self::LINK_MAX_LENGTH || preg_match('#^https?://\S+$#i', $url) !== 1) {
                throw $this->invalid('links', sprintf('darf nur Adressen enthalten, die mit http:// oder https:// beginnen (höchstens %d Zeichen)', self::LINK_MAX_LENGTH));
            }
            $links[] = $url;
        }

        return $links;
    }

    private function normalizePriority(mixed $value): int
    {
        if (!is_int($value) || $value < self::PRIORITY_MIN || $value > self::PRIORITY_MAX) {
            throw $this->invalid('priority', sprintf('muss eine ganze Zahl von %d (am wichtigsten) bis %d sein', self::PRIORITY_MIN, self::PRIORITY_MAX));
        }

        return $value;
    }

    private function normalizeDepth(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::DEPTHS, true)) {
            throw $this->invalid('depth', 'muss einer dieser Werte sein: ' . implode(', ', self::DEPTHS));
        }

        return $value;
    }

    private function normalizeCategory(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $category = is_string($value) ? $this->categories->findBySlug($value) : null;
        if ($category === null || !$category['active']) {
            throw new DomainError(
                'unknown_category',
                sprintf('Die Kategorie „%s“ gibt es nicht oder sie ist nicht aktiv. Nimm einen Slug aus der Kategorienliste.', is_string($value) ? $value : '?'),
                ['field' => 'category']
            );
        }

        return (int) $category['id'];
    }

    /** @return list<array{id: int, uid: string, note: string|null, own_material: bool}> */
    private function normalizeMedia(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw $this->invalid('media', 'muss eine Liste von Objekten {uid, note?, own_material?} sein');
        }

        $attachments = [];
        foreach ($value as $entry) {
            $uid = is_array($entry) ? ($entry['uid'] ?? null) : null;
            $note = is_array($entry) ? ($entry['note'] ?? null) : null;
            $ownMaterial = is_array($entry) ? ($entry['own_material'] ?? false) : false;
            if (!is_string($uid) || ($note !== null && !is_string($note)) || !is_bool($ownMaterial)) {
                throw $this->invalid('media', 'darf nur Objekte der Form {uid: Text, note: Text, own_material: true|false} enthalten');
            }
            $note = $note === null ? null : trim($note);
            if ($note !== null && mb_strlen($note) > self::MEDIA_NOTE_MAX_LENGTH) {
                throw $this->invalid('media', sprintf('Hinweis zu „%s“ darf höchstens %d Zeichen lang sein', $uid, self::MEDIA_NOTE_MAX_LENGTH));
            }
            if (isset($attachments[$uid])) {
                throw $this->invalid('media', sprintf('nennt das Medium „%s“ mehrfach', $uid));
            }
            $medium = $this->media->findByUid($uid);
            if ($medium === null) {
                throw new DomainError('not_found', sprintf('Das Medium „%s“ gibt es nicht. Lade es zuerst hoch.', $uid), ['uid' => $uid]);
            }
            $attachments[$uid] = ['id' => (int) $medium['id'], 'uid' => $uid, 'note' => $note === '' ? null : $note, 'own_material' => $ownMaterial];
        }

        return array_values($attachments);
    }

    private function invalid(string $field, string $reason): DomainError
    {
        return new DomainError('invalid_value', sprintf('Das Feld „%s“ %s.', $field, $reason), ['field' => $field]);
    }

    private function notFound(int $id): DomainError
    {
        return new DomainError('not_found', sprintf('Den Auftrag %d gibt es nicht.', $id), ['id' => $id]);
    }
}
