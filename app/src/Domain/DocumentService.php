<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

final class DocumentService
{
    public const PUBLIC_KEYS = ['about', 'imprint', 'privacy'];

    public const INTERNAL_KEYS = ['editorial_guidelines', 'preferences'];

    /** Adresse der öffentlichen Seite je Dokument; interne Dokumente haben keine. */
    public const PUBLIC_SLUGS = ['about' => 'ueber', 'imprint' => 'impressum', 'privacy' => 'datenschutz'];

    private const MAX_CONTENT_LENGTH = 200000;
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_NOTE_LENGTH = 500;

    private DocumentRepository $documents;
    private ChangeService $changes;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, array $config)
    {
        $this->documents = new DocumentRepository($pdo);
        $this->changes = new ChangeService($pdo, $config);
        $this->audit = new AuditRepository($pdo);
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        $document = $this->documents->get($key);
        if ($document === null) {
            throw new DomainError('not_found', sprintf('Das Dokument „%s“ gibt es nicht. Gültig: %s.', $key, implode(', ', array_merge(self::PUBLIC_KEYS, self::INTERNAL_KEYS))), ['doc_key' => $key]);
        }

        return $document;
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->documents->all();
    }

    /**
     * Öffentliche Dokumente ändert nur der Inhaber direkt; sonst entsteht ein Vorschlag.
     *
     * @return array{applied: list<string>, change_id: int|null, revision: int|null}
     */
    public function update(string $key, string $content, ?string $title, ?string $note, Actor $actor): array
    {
        $current = $this->get($key);
        $this->assertValid($content, $title, $note);

        return Transaction::join($this->pdo, function () use ($key, $content, $title, $note, $actor, $current): array {
            if (!$actor->isOwner() && in_array($key, self::PUBLIC_KEYS, true)) {
                $changeId = $this->changes->propose('document', $key, [
                    'content' => $content,
                    'title' => $title,
                    'note' => $note,
                ], $actor);

                return ['applied' => [], 'change_id' => $changeId, 'revision' => null];
            }

            $revision = $this->write($current, $content, $title, $note, $actor);

            return ['applied' => ['content', 'title'], 'change_id' => null, 'revision' => $revision];
        });
    }

    /**
     * Übernahme eines Vorschlags; läuft in der Transaktion von ChangeService::apply.
     *
     * @param array<string, mixed> $payload
     */
    public function applyChange(string $key, array $payload): void
    {
        $content = $payload['content'] ?? null;
        $title = $payload['title'] ?? null;
        $note = $payload['note'] ?? null;
        if (!is_string($content) || !($title === null || is_string($title)) || !($note === null || is_string($note))) {
            throw new DomainError('invalid_value', 'Der Vorschlag enthält keinen gültigen Dokumentinhalt.', ['doc_key' => $key]);
        }

        $current = $this->get($key);
        $this->assertValid($content, $title, $note);
        $this->write($current, $content, $title, $note, Actor::owner());
    }

    /** @param array<string, mixed> $current */
    private function write(array $current, string $content, ?string $title, ?string $note, Actor $actor): int
    {
        $key = (string) $current['doc_key'];
        $newTitle = $title === null ? (string) $current['title'] : trim($title);

        $revision = $this->documents->updateContent($key, $newTitle, $content);
        $this->documents->insertRevision($key, $revision, $content, $note, $actor->name);
        $this->audit->record($actor, 'document.update', 'document:' . $key, [
            'revision' => $revision,
            'title_changed' => $newTitle !== $current['title'],
        ]);

        return $revision;
    }

    private function assertValid(string $content, ?string $title, ?string $note): void
    {
        if (trim($content) === '') {
            throw new DomainError('invalid_value', 'Der Inhalt darf nicht leer sein.', ['field' => 'content']);
        }
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new DomainError('invalid_value', sprintf('Der Inhalt darf höchstens %d Zeichen lang sein.', self::MAX_CONTENT_LENGTH), ['field' => 'content']);
        }
        if ($title !== null && (trim($title) === '' || mb_strlen(trim($title)) > self::MAX_TITLE_LENGTH)) {
            throw new DomainError('invalid_value', sprintf('Der Titel muss 1 bis %d Zeichen lang sein.', self::MAX_TITLE_LENGTH), ['field' => 'title']);
        }
        if ($note !== null && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new DomainError('invalid_value', sprintf('Die Anmerkung darf höchstens %d Zeichen lang sein.', self::MAX_NOTE_LENGTH), ['field' => 'note']);
        }
    }
}
