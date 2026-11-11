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

    /** @return array{reaction: string, note: string|null}|null */
    public function find(int $postId): ?array
    {
        $statement = $this->pdo->prepare('SELECT reaction, note FROM post_reactions WHERE post_id = ?');
        $statement->execute([$postId]);
        $row = $statement->fetch();

        return $row === false ? null : ['reaction' => (string) $row['reaction'], 'note' => $row['note']];
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

    /** @return array<int, string> Artikel-ID → Reaktion */
    public function all(): array
    {
        $reactions = [];
        foreach ($this->pdo->query('SELECT post_id, reaction FROM post_reactions ORDER BY post_id')->fetchAll() as $row) {
            $reactions[(int) $row['post_id']] = (string) $row['reaction'];
        }

        return $reactions;
    }

    /** @return array<int, array{more: int, less: int}> Kategorie-ID (Hauptkategorie des Artikels) → Anzahl der Reaktionen */
    public function countsByPrimaryCategory(int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT pc.category_id, r.reaction, COUNT(*) AS total
             FROM post_reactions r JOIN post_categories pc ON pc.post_id = r.post_id AND pc.is_primary = 1
             WHERE r.updated_at >= UTC_TIMESTAMP() - INTERVAL ? DAY
             GROUP BY pc.category_id, r.reaction'
        );
        $statement->bindValue(1, $days, PDO::PARAM_INT);
        $statement->execute();

        $counts = [];
        foreach ($statement->fetchAll() as $row) {
            $categoryId = (int) $row['category_id'];
            $counts[$categoryId] ??= ['more' => 0, 'less' => 0];
            $counts[$categoryId][(string) $row['reaction']] = (int) $row['total'];
        }

        return $counts;
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
