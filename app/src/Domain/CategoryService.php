<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

final class CategoryService
{
    public const PUBLIC_FIELDS = ['slug', 'name', 'description', 'parent_id', 'position', 'active'];

    public const INTERNAL_FIELDS = ['interest', 'keywords', 'avoid'];

    private const CREATE_DEFAULTS = [
        'description' => null,
        'parent_id' => null,
        'position' => 0,
        'interest' => 3,
        'keywords' => [],
        'avoid' => [],
        'active' => true,
    ];

    private const MAX_DESCRIPTION_LENGTH = 10000;
    private const MAX_LIST_ENTRIES = 30;
    private const MAX_LIST_ENTRY_LENGTH = 60;

    private CategoryRepository $categories;
    private ChangeService $changes;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, array $config)
    {
        $this->categories = new CategoryRepository($pdo);
        $this->changes = new ChangeService($pdo, $config);
        $this->audit = new AuditRepository($pdo);
    }

    /** @return list<array<string, mixed>> */
    public function list(bool $includeInactive = false): array
    {
        return $this->categories->all($includeInactive);
    }

    /**
     * @param int|string $idOrSlug Zahl = ID, Text = Slug
     * @return array<string, mixed>
     */
    public function get(int|string $idOrSlug): array
    {
        $category = is_int($idOrSlug) ? $this->categories->find($idOrSlug) : $this->categories->findBySlug($idOrSlug);
        if ($category === null) {
            throw new DomainError('not_found', sprintf('Die Kategorie „%s“ gibt es nicht.', (string) $idOrSlug), ['category' => $idOrSlug]);
        }

        return $category;
    }

    /**
     * Inhaber: sofort. Andere: ein Vorschlag mit allen Feldern.
     *
     * @param array<string, mixed> $fields
     * @return array{category?: array<string, mixed>, change_id?: int}
     */
    public function create(array $fields, Actor $actor): array
    {
        $normalized = $this->normalizeForCreate($fields);

        return Transaction::join($this->pdo, function () use ($normalized, $actor): array {
            if (!$actor->isOwner()) {
                return ['change_id' => $this->changes->propose('category', 'new', $normalized, $actor)];
            }

            return ['category' => $this->insert($normalized, $actor)];
        });
    }

    /**
     * Interne Felder sofort, öffentliche nur durch den Inhaber, sonst als ein Vorschlag.
     *
     * @param array<string, mixed> $fields
     * @return array{applied: list<string>, change_id: int|null}
     */
    public function update(int $id, array $fields, Actor $actor): array
    {
        $current = $this->get($id);
        $normalized = $this->normalize($fields, $id);

        return Transaction::join($this->pdo, function () use ($id, $normalized, $actor, $current): array {
            $direct = [];
            $proposed = [];
            foreach ($normalized as $field => $value) {
                if ($actor->isOwner() || in_array($field, self::INTERNAL_FIELDS, true)) {
                    $direct[$field] = $value;
                } else {
                    $proposed[$field] = $value;
                }
            }

            $this->write($id, $current, $direct, $actor);
            $changeId = null;
            if ($proposed !== []) {
                $changeId = $this->changes->propose('category', (string) $id, $proposed, $actor);
            }

            return ['applied' => array_keys($direct), 'change_id' => $changeId];
        });
    }

    /**
     * Übernahme eines Vorschlags; läuft in der Transaktion von ChangeService::apply.
     *
     * @param array<string, mixed> $payload
     */
    public function applyChange(string $key, array $payload): void
    {
        if ($key === 'new') {
            $this->insert($this->normalizeForCreate($payload), Actor::owner());

            return;
        }

        $id = (int) $key;
        $this->write($id, $this->get($id), $this->normalize($payload, $id), Actor::owner());
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalizeForCreate(array $fields): array
    {
        foreach (['slug', 'name'] as $required) {
            if (!array_key_exists($required, $fields)) {
                throw new DomainError('invalid_value', sprintf('Das Feld „%s“ ist beim Anlegen Pflicht.', $required), ['field' => $required]);
            }
        }

        return $this->normalize(array_replace(self::CREATE_DEFAULTS, $fields), null);
    }

    /**
     * @param array<string, mixed> $normalized vollständig
     * @return array<string, mixed>
     */
    private function insert(array $normalized, Actor $actor): array
    {
        $id = $this->categories->insert($normalized);
        $this->audit->record($actor, 'category.create', 'category:' . $id, ['slug' => $normalized['slug']]);

        return $this->get($id);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $fields
     */
    private function write(int $id, array $current, array $fields, Actor $actor): void
    {
        if ($fields === []) {
            return;
        }

        $old = [];
        foreach (array_keys($fields) as $field) {
            $old[$field] = $current[$field];
        }
        $this->categories->update($id, $fields);
        $this->audit->record($actor, 'category.update', 'category:' . $id, ['old' => $old, 'new' => $fields]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalize(array $fields, ?int $id): array
    {
        $normalized = [];
        foreach ($fields as $field => $value) {
            $field = (string) $field;
            $normalized[$field] = match ($field) {
                'slug' => $this->normalizeSlug($value, $id),
                'name' => $this->normalizeName($value),
                'description' => $this->normalizeDescription($value),
                'parent_id' => $this->normalizeParent($value, $id),
                'position' => $this->normalizeInteger($field, $value, -1000000, 1000000),
                'interest' => $this->normalizeInteger($field, $value, 0, 5),
                'keywords', 'avoid' => $this->normalizeList($field, $value),
                'active' => $this->normalizeActive($value),
                default => throw $this->invalid($field, 'ist kein Kategorie-Feld. Gültig: ' . implode(', ', array_merge(self::PUBLIC_FIELDS, self::INTERNAL_FIELDS))),
            };
        }

        return $normalized;
    }

    private function normalizeSlug(mixed $value, ?int $id): string
    {
        if (!is_string($value) || strlen($value) > 80 || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) !== 1) {
            throw $this->invalid('slug', 'muss aus Kleinbuchstaben, Ziffern und einzelnen Bindestrichen bestehen (höchstens 80 Zeichen), z. B. „kuenstliche-intelligenz“');
        }
        $existing = $this->categories->findBySlug($value);
        if ($existing !== null && $existing['id'] !== $id) {
            throw new DomainError('slug_taken', sprintf('Der Slug „%s“ ist schon vergeben. Wähle einen anderen.', $value), ['field' => 'slug']);
        }

        return $value;
    }

    private function normalizeName(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 120) {
            throw $this->invalid('name', 'muss 1 bis 120 Zeichen lang sein');
        }

        return trim($value);
    }

    private function normalizeDescription(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > self::MAX_DESCRIPTION_LENGTH) {
            throw $this->invalid('description', sprintf('muss ein Text mit höchstens %d Zeichen oder null sein', self::MAX_DESCRIPTION_LENGTH));
        }

        return trim($value);
    }

    private function normalizeParent(mixed $value, ?int $id): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value)) {
            throw $this->invalid('parent_id', 'muss die ID einer Kategorie oder null sein');
        }
        if ($value === $id) {
            throw $this->invalid('parent_id', 'darf nicht die Kategorie selbst sein');
        }

        $parent = $this->categories->find($value);
        if ($parent === null) {
            throw $this->invalid('parent_id', sprintf('verweist auf die Kategorie %d, die es nicht gibt', $value));
        }
        if ($parent['parent_id'] !== null) {
            throw $this->invalid('parent_id', 'darf keine Unterkategorie sein — es gibt nur zwei Ebenen');
        }
        if ($id !== null && $this->categories->hasChildren($id)) {
            throw $this->invalid('parent_id', 'ist nicht möglich: die Kategorie hat selbst Unterkategorien — es gibt nur zwei Ebenen');
        }

        return $value;
    }

    private function normalizeInteger(string $field, mixed $value, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw $this->invalid($field, sprintf('muss eine ganze Zahl von %d bis %d sein', $min, $max));
        }

        return $value;
    }

    /** @return list<string> */
    private function normalizeList(string $field, mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_LIST_ENTRIES) {
            throw $this->invalid($field, sprintf('muss eine Liste mit höchstens %d Einträgen sein', self::MAX_LIST_ENTRIES));
        }

        $entries = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '' || mb_strlen(trim($entry)) > self::MAX_LIST_ENTRY_LENGTH) {
                throw $this->invalid($field, sprintf('darf nur Texte mit 1 bis %d Zeichen enthalten', self::MAX_LIST_ENTRY_LENGTH));
            }
            $entries[] = trim($entry);
        }

        return $entries;
    }

    private function normalizeActive(mixed $value): bool
    {
        if (!is_bool($value)) {
            throw $this->invalid('active', 'muss true oder false sein');
        }

        return $value;
    }

    private function invalid(string $field, string $reason): DomainError
    {
        return new DomainError('invalid_value', sprintf('Das Feld „%s“ %s.', $field, $reason), ['field' => $field]);
    }
}
