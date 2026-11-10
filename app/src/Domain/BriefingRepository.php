<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

/** Abfragen für das Briefing: Kategorie-Kennzahlen, letzte Artikel, zurückgegebene Entwürfe. */
final class BriefingRepository
{
    private const JSON_FLAGS = JSON_THROW_ON_ERROR;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Live-Artikel einer Kategorie: veröffentlicht und nicht archiviert, direkte Zuordnung.
     *
     * @return array<int, array{interest: int, post_count: int, last_published_at: string|null, days_since_last: int|null}> Schlüssel = Kategorie-ID
     */
    public function liveStatsByCategory(): array
    {
        $statement = $this->pdo->query(
            "SELECT c.id, c.interest, COUNT(p.id) AS post_count, MAX(p.published_at) AS last_published_at,
                    DATEDIFF(UTC_DATE(), DATE(MAX(p.published_at))) AS days_since_last
             FROM categories c
             LEFT JOIN post_categories pc ON pc.category_id = c.id
             LEFT JOIN posts p ON p.id = pc.post_id AND p.published_revision IS NOT NULL AND p.status <> 'archived'
             GROUP BY c.id, c.interest"
        );

        $stats = [];
        foreach ($statement->fetchAll() as $row) {
            $stats[(int) $row['id']] = [
                'interest' => (int) $row['interest'],
                'post_count' => (int) $row['post_count'],
                'last_published_at' => $row['last_published_at'],
                'days_since_last' => $row['days_since_last'] === null ? null : (int) $row['days_since_last'],
            ];
        }

        return $stats;
    }

    /** @return list<array<string, mixed>> neueste Änderung zuerst, alle Status */
    public function recentPosts(int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT p.id, p.slug, p.title, p.status, p.tags, p.published_at,
                    (SELECT c.slug FROM post_categories pc JOIN categories c ON c.id = pc.category_id
                     WHERE pc.post_id = p.id ORDER BY pc.is_primary DESC, c.slug LIMIT 1) AS primary_category
             FROM posts p ORDER BY p.updated_at DESC, p.id DESC LIMIT ?"
        );
        $statement->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'status' => $row['status'],
            'primary_category' => $row['primary_category'],
            'tags' => json_decode((string) $row['tags'], true, 512, self::JSON_FLAGS),
            'published_at' => $row['published_at'],
        ], $statement->fetchAll());
    }

    /** @return list<array<string, mixed>> Entwürfe, die der Inhaber mit einer Anmerkung zurückgegeben hat */
    public function returnedDrafts(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, title, review_note, updated_at FROM posts
             WHERE status = 'draft' AND review_note IS NOT NULL AND review_note <> ''
             ORDER BY updated_at DESC, id DESC"
        )->fetchAll();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $rows);
    }
}
