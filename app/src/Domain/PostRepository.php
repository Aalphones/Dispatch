<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Like;
use PDO;

final class PostRepository
{
    /** Spalten, die insert/update schreiben dürfen — nur diese Namen landen im SQL. */
    private const WRITABLE = [
        'slug', 'title', 'subtitle', 'kicker', 'excerpt', 'content_html', 'status', 'tags', 'layout', 'accent',
        'cover_media_id', 'seo_title', 'seo_description', 'origin', 'queue_item_id', 'depth', 'editor_notes',
        'review_note', 'ai_assisted', 'word_count', 'revision',
    ];

    private const LIST_COLUMNS = [
        'id', 'slug', 'title', 'subtitle', 'kicker', 'excerpt', 'status', 'tags', 'layout', 'accent',
        'cover_media_id', 'seo_title', 'seo_description', 'origin', 'queue_item_id', 'depth', 'review_note',
        'ai_assisted', 'word_count', 'revision', 'published_revision', 'published_at', 'created_at', 'updated_at',
    ];

    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->findOne('id', $id);
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->findOne('slug', $slug);
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM posts WHERE slug = ? AND id <> ? LIMIT 1');
        $statement->execute([$slug, $exceptId ?? 0]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $fields */
    public function insert(array $fields): int
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO posts (%s, created_at, updated_at) VALUES (%s, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        ));
        $statement->execute($this->encode($fields));

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        $columns = array_keys($fields);
        $this->assertWritable($columns);

        $assignments = array_map(static fn (string $column): string => $column . ' = ?', $columns);
        $statement = $this->pdo->prepare(
            'UPDATE posts SET ' . implode(', ', $assignments) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([...$this->encode($fields), $id]);
    }

    /**
     * Ohne `content_html` und `editor_notes` — Listen sollen klein bleiben.
     *
     * @param list<string> $statuses leer = alle
     * @param string|null $categorySlug direkte Zuordnung, Haupt- oder Nebenkategorie
     * @param string|null $tag exakter Name
     * @param int|null $year Jahr der Veröffentlichung, bei Unveröffentlichten das der Erstellung
     * @return list<array<string, mixed>>
     */
    public function list(array $statuses, int $limit, int $offset, ?string $categorySlug = null, ?string $tag = null, ?int $year = null): array
    {
        $columns = array_map(static fn (string $column): string => 'p.' . $column, self::LIST_COLUMNS);
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM posts p';
        $conditions = [];
        $parameters = [];

        if ($categorySlug !== null) {
            $sql .= ' JOIN post_categories pc ON pc.post_id = p.id JOIN categories c ON c.id = pc.category_id';
            $conditions[] = 'c.slug = ?';
            $parameters[] = $categorySlug;
        }
        if ($statuses !== []) {
            $conditions[] = 'p.status IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')';
            array_push($parameters, ...$statuses);
        }
        if ($tag !== null) {
            $conditions[] = 'JSON_CONTAINS(p.tags, JSON_QUOTE(?))';
            $parameters[] = $tag;
        }
        if ($year !== null) {
            $conditions[] = 'YEAR(COALESCE(p.published_at, p.created_at)) = ?';
            $parameters[] = $year;
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ? OFFSET ?';

        $statement = $this->pdo->prepare($sql);
        $position = 1;
        foreach ($parameters as $parameter) {
            $statement->bindValue($position++, $parameter, is_int($parameter) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue($position++, max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue($position, max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    /**
     * Treffer im Titel stehen vor Treffern nur in Untertitel, Auszug oder Text.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit): array
    {
        $pattern = Like::contains($term);
        $escape = Like::ESCAPE_CLAUSE;
        $statement = $this->pdo->prepare(
            "SELECT id, slug, title, status, excerpt, published_at, updated_at FROM posts
             WHERE title LIKE ? $escape OR subtitle LIKE ? $escape OR excerpt LIKE ? $escape OR content_html LIKE ? $escape
             ORDER BY CASE WHEN title LIKE ? $escape THEN 0 ELSE 1 END, updated_at DESC, id DESC
             LIMIT ?"
        );
        foreach (range(1, 5) as $position) {
            $statement->bindValue($position, $pattern);
        }
        $statement->bindValue(6, max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $statement->fetchAll());
    }

    /** @return array<string, int> Status => Anzahl, nur vorhandene */
    public function countByStatus(): array
    {
        $counts = [];
        foreach ($this->pdo->query('SELECT status, COUNT(*) AS total FROM posts GROUP BY status')->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** Entwürfe, die der Inhaber mit einer Anmerkung zurückgegeben hat. */
    public function countReturned(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'draft' AND review_note IS NOT NULL AND review_note <> ''")->fetchColumn();
    }

    public function mediaUid(int $mediaId): ?string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM media WHERE id = ?');
        $statement->execute([$mediaId]);
        $uid = $statement->fetchColumn();

        return $uid === false ? null : (string) $uid;
    }

    /** @param list<array{category_id: int, is_primary: bool}> $rows */
    public function replaceCategories(int $postId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM post_categories WHERE post_id = ?')->execute([$postId]);

        $insert = $this->pdo->prepare('INSERT INTO post_categories (post_id, category_id, is_primary) VALUES (?, ?, ?)');
        foreach ($rows as $row) {
            $insert->execute([$postId, $row['category_id'], $row['is_primary'] ? 1 : 0]);
        }
    }

    /**
     * Löst alle Zuordnungen zur Kategorie `$fromId` (vor deren Löschen; läuft in der Transaktion des Aufrufers).
     * Artikel mit weiteren Kategorien verlieren nur diese Zuordnung — war sie die Hauptkategorie, wird die übrige
     * mit dem alphabetisch ersten Namen Hauptkategorie. Artikel ohne weitere Kategorie bekommen `$toId` als Hauptkategorie.
     *
     * @return int Anzahl betroffener Artikel
     */
    public function moveCategoryLinks(int $fromId, ?int $toId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT pc.post_id, pc.is_primary,
                    (SELECT COUNT(*) FROM post_categories other
                     WHERE other.post_id = pc.post_id AND other.category_id <> pc.category_id) AS other_count
             FROM post_categories pc WHERE pc.category_id = ? ORDER BY pc.post_id FOR UPDATE'
        );
        $statement->execute([$fromId]);
        $links = $statement->fetchAll();

        foreach ($links as $link) {
            $postId = (int) $link['post_id'];
            if ((int) $link['other_count'] === 0) {
                $this->retargetOnlyCategory($postId, $fromId, $toId);
                continue;
            }

            $this->pdo->prepare('DELETE FROM post_categories WHERE post_id = ? AND category_id = ?')->execute([$postId, $fromId]);
            if ((int) $link['is_primary'] === 1) {
                $this->ensurePrimaryCategory($postId);
            }
        }

        return count($links);
    }

    private function retargetOnlyCategory(int $postId, int $fromId, ?int $toId): void
    {
        if ($toId === null) {
            throw new \LogicException(sprintf('Artikel %d hätte ohne Ersatz-Kategorie keine Kategorie mehr.', $postId));
        }

        $this->pdo->prepare('UPDATE post_categories SET category_id = ?, is_primary = 1 WHERE post_id = ? AND category_id = ?')
            ->execute([$toId, $postId, $fromId]);
    }

    private function ensurePrimaryCategory(int $postId): void
    {
        $primary = $this->pdo->prepare('SELECT 1 FROM post_categories WHERE post_id = ? AND is_primary = 1 LIMIT 1');
        $primary->execute([$postId]);
        if ($primary->fetchColumn() !== false) {
            return;
        }

        // Getrennt gelesen: MySQL erlaubt im UPDATE keine Unterabfrage auf dieselbe Tabelle (Fehler 1093).
        $first = $this->pdo->prepare(
            'SELECT pc.category_id FROM post_categories pc JOIN categories c ON c.id = pc.category_id
             WHERE pc.post_id = ? ORDER BY c.name, c.id LIMIT 1'
        );
        $first->execute([$postId]);
        $categoryId = $first->fetchColumn();
        if ($categoryId === false) {
            return;
        }

        $this->pdo->prepare('UPDATE post_categories SET is_primary = 1 WHERE post_id = ? AND category_id = ?')
            ->execute([$postId, (int) $categoryId]);
    }

    /** @return list<array{slug: string, name: string, primary: bool}> Hauptkategorie zuerst */
    public function categories(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.slug, c.name, pc.is_primary FROM post_categories pc
             JOIN categories c ON c.id = pc.category_id
             WHERE pc.post_id = ? ORDER BY pc.is_primary DESC, c.name, c.id'
        );
        $statement->execute([$postId]);

        return array_map(
            static fn (array $row): array => [
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'primary' => (int) $row['is_primary'] === 1,
            ],
            $statement->fetchAll()
        );
    }

    /** @param list<array<string, mixed>> $rows normalisiert, in Anzeige-Reihenfolge */
    public function replaceSources(int $postId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM sources WHERE post_id = ?')->execute([$postId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO sources (post_id, ref, position, kind, title, url, publisher, author, published, accessed, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (array_values($rows) as $position => $row) {
            $insert->execute([
                $postId, $row['ref'], $position, $row['kind'], $row['title'], $row['url'],
                $row['publisher'], $row['author'], $row['published'], $row['accessed'], $row['note'],
            ]);
        }
    }

    /** @return list<array<string, mixed>> in Reihenfolge der Liste */
    public function sources(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ref, kind, title, url, publisher, author, published, accessed, note
             FROM sources WHERE post_id = ? ORDER BY position'
        );
        $statement->execute([$postId]);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $snapshot */
    public function insertRevision(int $postId, int $revision, array $snapshot, string $actor): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO post_revisions (post_id, revision, snapshot, actor, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $statement->execute([$postId, $revision, json_encode($snapshot, self::JSON_FLAGS), $actor]);
    }

    /** @return array<string, mixed>|null der Snapshot */
    public function revision(int $postId, int $revision): ?array
    {
        $statement = $this->pdo->prepare('SELECT snapshot FROM post_revisions WHERE post_id = ? AND revision = ?');
        $statement->execute([$postId, $revision]);
        $snapshot = $statement->fetchColumn();

        return $snapshot === false ? null : json_decode((string) $snapshot, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{revision: int, actor: string, created_at: string}> neueste zuerst */
    public function revisions(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT revision, actor, created_at FROM post_revisions WHERE post_id = ? ORDER BY revision DESC'
        );
        $statement->execute([$postId]);

        return $statement->fetchAll();
    }

    /**
     * Der Live-Stand aller veröffentlichten Artikel — die einzige Quelle für die Website.
     * Der Snapshot der freigegebenen Revision, nie der Arbeitsstand.
     *
     * @return list<array<string, mixed>> Snapshot plus `published_at` und `updated_at` (= Erstellzeit der Live-Revision), neueste zuerst
     */
    public function livePosts(): array
    {
        $statement = $this->pdo->query(
            "SELECT r.snapshot, p.published_at, r.created_at AS updated_at FROM posts p
             JOIN post_revisions r ON r.post_id = p.id AND r.revision = p.published_revision
             WHERE p.published_revision IS NOT NULL AND p.status <> 'archived'
             ORDER BY p.published_at DESC, p.id DESC"
        );

        $posts = [];
        foreach ($statement->fetchAll() as $row) {
            $snapshot = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $snapshot['published_at'] = $row['published_at'];
            $snapshot['updated_at'] = $row['updated_at'];
            $posts[] = $snapshot;
        }

        return $posts;
    }

    /**
     * Titel der Live-Stände (nicht des Arbeitsstands), gleiche Bedingung wie `livePosts()`.
     *
     * @return list<string>
     */
    public function liveTitles(?int $exceptId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT r.snapshot FROM posts p
             JOIN post_revisions r ON r.post_id = p.id AND r.revision = p.published_revision
             WHERE p.published_revision IS NOT NULL AND p.status <> 'archived' AND p.id <> ?"
        );
        $statement->execute([$exceptId ?? 0]);

        $titles = [];
        foreach ($statement->fetchAll() as $row) {
            $snapshot = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $titles[] = (string) $snapshot['title'];
        }

        return $titles;
    }

    /** Sperrt die Zeile bis zum Ende der Transaktion, damit zwei Übergänge sich nicht überholen. */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM posts WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /**
     * Statuswechsel: nur `status`, `review_note`, `published_revision`; `touch_published_at => true`
     * setzt `published_at` einmalig auf jetzt (UTC) und lässt einen vorhandenen Wert stehen.
     *
     * @param array<string, mixed> $fields
     */
    public function setStatus(int $id, array $fields): void
    {
        $assignments = [];
        $values = [];
        foreach ($fields as $column => $value) {
            if ($column === 'touch_published_at') {
                $assignments[] = 'published_at = COALESCE(published_at, UTC_TIMESTAMP())';
                continue;
            }
            if (!in_array($column, ['status', 'review_note', 'published_revision'], true)) {
                throw new \InvalidArgumentException('Unbekannte Statusspalte: ' . $column);
            }
            $assignments[] = $column . ' = ?';
            $values[] = $value;
        }

        $statement = $this->pdo->prepare(
            'UPDATE posts SET ' . implode(', ', $assignments) . ', updated_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([...$values, $id]);
    }

    /** Löscht den Artikel; Revisionen, Quellen und Kategorie-Zuordnungen folgen per Fremdschlüssel (CASCADE). */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $column, int|string $value): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM posts WHERE ' . $column . ' = ?');
        $statement->execute([$value]);
        $row = $statement->fetch();

        return $row === false ? null : $this->decode($row);
    }

    /** @param list<string> $columns */
    private function assertWritable(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, self::WRITABLE, true)) {
                throw new \InvalidArgumentException('Unbekannte Artikel-Spalte: ' . $column);
            }
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<mixed>
     */
    private function encode(array $fields): array
    {
        if (array_key_exists('tags', $fields)) {
            $fields['tags'] = json_encode($fields['tags'], self::JSON_FLAGS);
        }
        if (array_key_exists('ai_assisted', $fields)) {
            $fields['ai_assisted'] = $fields['ai_assisted'] ? 1 : 0;
        }

        return array_values($fields);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        $row['tags'] = json_decode((string) $row['tags'], true, 512, JSON_THROW_ON_ERROR);
        $row['ai_assisted'] = (int) $row['ai_assisted'] === 1;

        return $row;
    }
}
