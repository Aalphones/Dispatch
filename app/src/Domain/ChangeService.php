<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

/**
 * Änderungsvorschläge für öffentlich Sichtbares. Die Fach-Services legen Vorschläge hier an und
 * bekommen sie bei der Übernahme zurück (applyChange) — so gilt dieselbe Prüfung wie bei direkter Änderung.
 * Die Fach-Services werden erst bei Bedarf gebaut: sie bauen selbst einen ChangeService.
 */
final class ChangeService
{
    private const ENTITIES = ['document', 'media', 'category', 'setting'];

    private ChangeRepository $changes;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->changes = new ChangeRepository($pdo);
        $this->audit = new AuditRepository($pdo);
    }

    /** @param array<string, mixed> $payload */
    public function propose(string $entity, string $key, array $payload, Actor $actor): int
    {
        if (!in_array($entity, self::ENTITIES, true)) {
            throw new DomainError('invalid_value', sprintf('Unbekannter Bereich „%s“. Gültig: %s.', $entity, implode(', ', self::ENTITIES)), ['field' => 'entity']);
        }

        return Transaction::join($this->pdo, function () use ($entity, $key, $payload, $actor): int {
            $id = $this->changes->insert($entity, $key, $payload, $actor->name);
            $this->audit->record($actor, 'change.propose', 'change:' . $id, ['entity' => $entity, 'key' => $key]);

            return $id;
        });
    }

    /**
     * Je Eintrag zusätzlich `before`: der aktuelle Wert der betroffenen Felder.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $status = 'pending'): array
    {
        $entries = [];
        foreach ($this->changes->list($status) as $change) {
            $change['before'] = $this->before($change);
            $entries[] = $change;
        }

        return $entries;
    }

    public function apply(int $id, Actor $actor): void
    {
        $this->assertOwner($actor, 'Vorschläge übernehmen');

        Transaction::join($this->pdo, function () use ($id, $actor): void {
            $change = $this->lockPending($id);
            $this->dispatchToService((string) $change['entity'], (string) $change['entity_key'], $change['payload']);
            $this->changes->decide($id, 'applied', $actor->name);
            $this->audit->record($actor, 'change.apply', 'change:' . $id, [
                'entity' => $change['entity'],
                'key' => $change['entity_key'],
            ]);
        });
    }

    public function reject(int $id, Actor $actor): void
    {
        $this->assertOwner($actor, 'Vorschläge ablehnen');

        Transaction::join($this->pdo, function () use ($id, $actor): void {
            $change = $this->lockPending($id);
            $this->changes->decide($id, 'rejected', $actor->name);
            $this->audit->record($actor, 'change.reject', 'change:' . $id, [
                'entity' => $change['entity'],
                'key' => $change['entity_key'],
            ]);
        });
    }

    private function assertOwner(Actor $actor, string $what): void
    {
        if (!$actor->isOwner()) {
            throw new DomainError('owner_only', sprintf('%s darf nur der Inhaber (Login im Redaktionsmodus).', ucfirst($what)));
        }
    }

    /** @return array<string, mixed> */
    private function lockPending(int $id): array
    {
        $change = $this->changes->find($id, true);
        if ($change === null) {
            throw new DomainError('not_found', sprintf('Den Vorschlag %d gibt es nicht.', $id), ['change_id' => $id]);
        }
        if ($change['status'] !== 'pending') {
            throw new DomainError('not_pending', sprintf('Der Vorschlag %d ist schon entschieden (%s).', $id, $change['status']), ['change_id' => $id]);
        }

        return $change;
    }

    /** @param array<string, mixed> $payload */
    private function dispatchToService(string $entity, string $key, array $payload): void
    {
        match ($entity) {
            'setting' => (new SettingService($this->pdo, $this->config))->applyChange($key, $payload),
            'document' => (new DocumentService($this->pdo, $this->config))->applyChange($key, $payload),
            'category' => (new CategoryService($this->pdo, $this->config))->applyChange($key, $payload),
            default => throw new DomainError('not_supported', sprintf('Vorschläge für „%s“ gibt es noch nicht.', $entity), ['entity' => $entity]),
        };
    }

    /**
     * @param array<string, mixed> $change
     * @return array<string, mixed>|null
     */
    private function before(array $change): ?array
    {
        $payload = $change['payload'];
        $key = (string) $change['entity_key'];

        switch ($change['entity']) {
            case 'setting':
                $settings = new SettingService($this->pdo, $this->config);
                $before = [];
                foreach (array_keys($payload) as $name) {
                    $before[$name] = in_array($name, array_keys(SettingService::DEFAULTS), true) ? $settings->get($name) : null;
                }

                return $before;

            case 'document':
                $document = (new DocumentRepository($this->pdo))->get($key);

                return $document === null ? null : ['title' => $document['title'], 'content' => $document['content']];

            case 'category':
                if ($key === 'new') {
                    return null;
                }
                $category = (new CategoryRepository($this->pdo))->find((int) $key);
                if ($category === null) {
                    return null;
                }

                return array_intersect_key($category, $payload);
        }

        return null;
    }
}
