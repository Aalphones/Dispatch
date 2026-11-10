<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class ReactionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function postExists(int $postId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM posts WHERE id = ?');
        $statement->execute([$postId]);

        return $statement->fetchColumn() !== false;
    }

    public function upsert(int $postId, string $reaction, ?string $note): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO post_reactions (post_id, reaction, note, updated_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), note = VALUES(note), updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([$postId, $reaction, $note]);
    }

    public function delete(int $postId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM post_reactions WHERE post_id = ?');
        $statement->execute([$postId]);
    }

    /** @return list<array<string, mixed>> neueste zuerst, ohne Kategorien */
    public function recent(int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.post_id, p.slug, p.title, r.reaction, r.note, r.updated_at, p.tags
             FROM post_reactions r JOIN posts p ON p.id = r.post_id
             WHERE r.updated_at >= UTC_TIMESTAMP() - INTERVAL ? DAY
             ORDER BY r.updated_at DESC, r.post_id DESC'
        );
        $statement->bindValue(1, $days, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /** @return list<string> Kategorie-Slugs, Hauptkategorie zuerst */
    public function categorySlugs(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.slug FROM post_categories pc JOIN categories c ON c.id = pc.category_id
             WHERE pc.post_id = ? ORDER BY pc.is_primary DESC, c.slug'
        );
        $statement->execute([$postId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
