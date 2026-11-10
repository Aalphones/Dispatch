<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

/** „Mehr davon / Weniger“: eine aktuelle Reaktion je Artikel, gesetzt nur vom Inhaber oder der CLI (ADR-019). */
final class ReactionService
{
    private const REACTIONS = ['more', 'less'];
    private const MAX_NOTE_LENGTH = 500;

    private ReactionRepository $reactions;
    private AuditRepository $audit;

    public function __construct(private readonly PDO $pdo)
    {
        $this->reactions = new ReactionRepository($pdo);
        $this->audit = new AuditRepository($pdo);
    }

    /** @param string|null $reaction `more`, `less`; null löscht die Reaktion */
    public function set(int $postId, ?string $reaction, ?string $note, Actor $actor): void
    {
        if (!$actor->isOwner() && $actor->name !== 'cli') {
            throw new DomainError('forbidden', 'Reaktionen setzt nur der Inhaber (Redaktionsmodus) oder die Kommandozeile.');
        }
        if ($reaction !== null && !in_array($reaction, self::REACTIONS, true)) {
            throw new DomainError('invalid_value', 'Die Reaktion muss more oder less sein (oder leer zum Löschen).', ['field' => 'reaction']);
        }
        if ($note !== null && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new DomainError('invalid_value', sprintf('Die Notiz darf höchstens %d Zeichen lang sein.', self::MAX_NOTE_LENGTH), ['field' => 'note']);
        }

        Transaction::join($this->pdo, function () use ($postId, $reaction, $note, $actor): void {
            if (!$this->reactions->postExists($postId)) {
                throw new DomainError('not_found', sprintf('Den Artikel %d gibt es nicht.', $postId), ['post_id' => $postId]);
            }

            if ($reaction === null) {
                $this->reactions->delete($postId);
            } else {
                $this->reactions->upsert($postId, $reaction, $note === '' ? null : $note);
            }
            $this->audit->record($actor, 'reaction.set', 'post:' . $postId, ['reaction' => $reaction]);
        });
    }

    /**
     * @return list<array<string, mixed>> neueste zuerst: post_id, slug, title, reaction, note, updated_at, categories, tags
     */
    public function recent(int $days = 90): array
    {
        $entries = [];
        foreach ($this->reactions->recent($days) as $row) {
            $postId = (int) $row['post_id'];
            $row['post_id'] = $postId;
            $row['categories'] = $this->reactions->categorySlugs($postId);
            $row['tags'] = json_decode((string) $row['tags'], true, 512, JSON_THROW_ON_ERROR);
            $entries[] = $row;
        }

        return $entries;
    }
}
