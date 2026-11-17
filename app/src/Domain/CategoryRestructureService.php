<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;
use RuntimeException;

/**
 * Einmalige Umstellung auf sieben Hauptkategorien mit Unterkategorien (ADR-037).
 *
 * `plan()` liest nur und beschreibt Vorher/Nachher. `apply()` sichert vorher alles, was es ändert, in eine Datei,
 * schreibt in einer Transaktion und prüft vor dem Commit; schlägt eine Prüfung fehl, bleibt die Datenbank unverändert.
 * `rollback()` stellt den gesicherten Stand wieder her. Artikeltexte, Titel, Status, Termine, Quellen, Medien und Slugs
 * der Artikel werden nie angefasst; geändert werden nur Kategorien, Zuordnungen und das Kategorie-Feld der gültigen
 * Revisions-Stände (die Website liest die Kategorien aus dem Snapshot).
 */
final class CategoryRestructureService
{
    private const BACKUP_VERSION = 1;
    private const BLOCKING_STATUSES = ['published', 'scheduled', 'draft', 'review'];

    private CategoryRepository $categories;
    private AuditRepository $audit;

    /** @var array<string, bool> Slugs, die vor dem Schreiben Hauptkategorien waren (ihre Adresse ändert sich beim Umhängen) */
    private array $formerMains = [];

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed>|null $spec Zielstruktur; Standard: app/data/kategorien-struktur.php
     */
    public function __construct(private readonly PDO $pdo, private readonly array $config, private ?array $spec = null)
    {
        $this->categories = new CategoryRepository($pdo);
        $this->audit = new AuditRepository($pdo);
        $this->spec ??= require (string) $config['app_dir'] . '/data/kategorien-struktur.php';
    }

    /**
     * Nur lesen: was würde sich ändern?
     *
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        $bySlug = $this->categoriesBySlug();
        $problems = [];

        return [
            'categories' => $this->planCategories($bySlug, $problems),
            'posts' => $this->planPosts($bySlug, $problems, $unmapped),
            'unmapped_posts' => $unmapped,
            'queue' => $this->planQueue($bySlug, $problems),
            'problems' => $problems,
            'already_applied' => $this->isApplied($bySlug),
        ];
    }

    /**
     * Sichert, schreibt, prüft. Bricht ab (und ändert nichts), wenn `plan()` Probleme meldet oder die Prüfung fehlschlägt.
     *
     * @return array{plan: array<string, mixed>, backup_file: string, backup: array<string, mixed>, summary: array<string, int>}
     */
    public function apply(Actor $actor): array
    {
        $plan = $this->plan();
        if ($plan['already_applied']) {
            throw new DomainError('already_applied', 'Die neue Kategorienstruktur ist schon angewendet. Zum Zurücksetzen gibt es rollback.');
        }
        if ($plan['problems'] !== []) {
            throw new DomainError('restructure_blocked', 'Die Umstellung ist blockiert: ' . implode(' | ', $plan['problems']), ['problems' => $plan['problems']]);
        }

        $backup = $this->buildBackup($plan);
        $file = $this->writeBackup($backup);

        $summary = Transaction::run($this->pdo, function () use ($plan, $actor): array {
            $summary = $this->write($plan);
            $this->verify($plan);
            $this->audit->record($actor, 'category.restructure', 'category:*', $summary);

            return $summary;
        });

        return ['plan' => $plan, 'backup_file' => basename($file), 'backup' => $backup, 'summary' => $summary];
    }

    /**
     * @param array<string, mixed> $backup Inhalt einer Sicherungsdatei von `apply()`
     * @return array<string, int>
     */
    public function rollback(array $backup, Actor $actor): array
    {
        if (($backup['version'] ?? null) !== self::BACKUP_VERSION || !isset($backup['categories'], $backup['post_ids'], $backup['snapshots'], $backup['queue_changed'], $backup['post_categories'], $backup['redirects'])) {
            throw new DomainError('invalid_backup', 'Die Sicherung ist unvollständig oder hat ein unbekanntes Format.');
        }

        return Transaction::run($this->pdo, function () use ($backup, $actor): array {
            $summary = $this->restore($backup);
            $this->audit->record($actor, 'category.restructure_rollback', 'category:*', $summary);

            return $summary;
        });
    }

    /** @return array<string, array<string, mixed>> Schlüssel = Slug, alle Zeilen auch inaktive */
    private function categoriesBySlug(): array
    {
        $bySlug = [];
        foreach ($this->categories->all(true) as $category) {
            $bySlug[(string) $category['slug']] = $category;
        }

        return $bySlug;
    }

    /**
     * @param array<string, array<string, mixed>> $bySlug
     * @param list<string> $problems
     * @return array<string, mixed>
     */
    private function planCategories(array $bySlug, array &$problems): array
    {
        $create = [];
        $update = [];
        $seen = [];
        foreach ($this->spec['tree'] as $mainSlug => $main) {
            $this->noteSlug($seen, $mainSlug, $problems);
            $existing = $bySlug[$mainSlug] ?? null;
            if ($existing !== null && $existing['parent_id'] !== null) {
                $problems[] = sprintf('Die Kategorie „%s“ ist eine Unterkategorie und kann nicht Hauptkategorie werden.', $mainSlug);
            }
            $entry = $this->entry($mainSlug, $main['name'], null, $existing);
            if ($existing === null) {
                $create[] = $entry;
            } else {
                $update[] = $entry;
            }

            foreach ($main['children'] as $childSlug => $child) {
                $this->noteSlug($seen, $childSlug, $problems);
                $existingChild = $bySlug[$childSlug] ?? null;
                if ($existingChild !== null && $this->categories->hasChildren((int) $existingChild['id'])) {
                    $problems[] = sprintf('Die Kategorie „%s“ hat selbst Unterkategorien und kann keine werden.', $childSlug);
                }
                $childEntry = $this->entry($childSlug, $child['name'], $mainSlug, $existingChild);
                if ($existingChild === null) {
                    $create[] = $childEntry;
                } else {
                    $update[] = $childEntry;
                }
            }
        }

        $retire = [];
        foreach ($this->spec['retire'] as $oldSlug => $targetSlug) {
            $old = $bySlug[$oldSlug] ?? null;
            if ($old === null) {
                $problems[] = sprintf('Die alte Kategorie „%s“ gibt es nicht.', $oldSlug);
                continue;
            }
            $retire[] = ['slug' => $oldSlug, 'name' => $old['name'], 'redirect_to' => $targetSlug, 'active_before' => $old['active']];
        }

        return ['create' => $create, 'update' => $update, 'retire' => $retire];
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function entry(string $slug, string $name, ?string $parent, ?array $existing): array
    {
        $entry = ['slug' => $slug, 'name' => $name, 'parent' => $parent];
        if ($existing !== null) {
            $entry['id'] = $existing['id'];
            $entry['was_name'] = $existing['name'];
            $entry['was_parent_id'] = $existing['parent_id'];
        }

        return $entry;
    }

    /** @param array<string, true> $seen @param list<string> $problems */
    private function noteSlug(array &$seen, string $slug, array &$problems): void
    {
        if (isset($seen[$slug])) {
            $problems[] = sprintf('Der Slug „%s“ kommt in der Zielstruktur doppelt vor.', $slug);
        }
        $seen[$slug] = true;
    }

    /**
     * @param array<string, array<string, mixed>> $bySlug
     * @param list<string> $problems
     * @param list<array<string, mixed>>|null $unmapped
     * @return list<array<string, mixed>>
     */
    private function planPosts(array $bySlug, array &$problems, ?array &$unmapped): array
    {
        $subSlugs = $this->subSlugs();
        $rows = [];
        $byId = [];
        foreach ($this->pdo->query('SELECT id, title, status, revision, published_revision FROM posts ORDER BY id')->fetchAll() as $row) {
            $byId[(int) $row['id']] = $row;
        }

        foreach ($this->spec['posts'] as $postId => $slug) {
            if (!isset($subSlugs[$slug])) {
                $problems[] = sprintf('Artikel %d zeigt auf „%s“, das keine Unterkategorie der Zielstruktur ist.', $postId, $slug);
            }
            $post = $byId[$postId] ?? null;
            if ($post === null) {
                $problems[] = sprintf('Artikel %d gibt es nicht.', $postId);
                continue;
            }

            $current = $this->postCategories($postId);
            $secondary = array_values(array_filter($current, static fn (array $row): bool => !$row['is_primary']));
            $rows[] = [
                'id' => $postId, 'title' => $post['title'], 'status' => $post['status'],
                'before' => array_map(static fn (array $row): string => $row['slug'] . ($row['is_primary'] ? '*' : ''), $current),
                'after' => $slug,
                'secondary_dropped' => array_map(static fn (array $row): string => $row['slug'], $secondary),
                'revisions' => $this->snapshotRevisions($post),
            ];
        }

        $unmapped = [];
        foreach ($byId as $postId => $post) {
            if (isset($this->spec['posts'][$postId])) {
                continue;
            }
            $unmapped[] = ['id' => $postId, 'title' => $post['title'], 'status' => $post['status'], 'categories' => array_map(static fn (array $row): string => $row['slug'], $this->postCategories($postId))];
            if (in_array($post['status'], self::BLOCKING_STATUSES, true)) {
                $problems[] = sprintf('Artikel %d („%s“, %s) hat keine Zuordnung in der Zielstruktur.', $postId, $post['title'], $post['status']);
            }
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, mixed>> $bySlug
     * @param list<string> $problems
     * @return list<array<string, mixed>>
     */
    private function planQueue(array $bySlug, array &$problems): array
    {
        $subSlugs = $this->subSlugs();
        $statement = $this->pdo->prepare(
            'SELECT q.id, q.title, q.status, q.claimed_by, q.post_id, q.category_id, c.slug AS category,
                    (q.status = \'claimed\' AND q.claim_expires <= UTC_TIMESTAMP()) AS claim_expired
             FROM queue_items q LEFT JOIN categories c ON c.id = q.category_id WHERE q.id = ?'
        );

        $rows = [];
        foreach ($this->spec['queue'] as $queueId => $slug) {
            if (!isset($subSlugs[$slug])) {
                $problems[] = sprintf('Queue-Eintrag %d zeigt auf „%s“, das keine Unterkategorie der Zielstruktur ist.', $queueId, $slug);
            }
            $statement->execute([$queueId]);
            $item = $statement->fetch();
            if ($item === false) {
                $problems[] = sprintf('Queue-Eintrag %d gibt es nicht.', $queueId);
                continue;
            }

            $action = 'set';
            $note = null;
            if ($item['status'] === 'claimed' && (int) $item['claim_expired'] === 1) {
                $note = sprintf('Reservierung von %s ist abgelaufen und bleibt unangetastet; nur die Kategorie wird gesetzt.', (string) $item['claimed_by']);
            } elseif ($item['status'] !== 'open') {
                $action = 'skip';
                $note = sprintf('Status „%s“: nicht angefasst.', (string) $item['status']);
            }
            $rows[] = [
                'id' => $queueId, 'title' => $item['title'], 'status' => $item['status'], 'before' => $item['category'],
                'after' => $slug, 'action' => $action, 'note' => $note,
            ];
        }

        return $rows;
    }

    /** @return array<string, true> alle Unterkategorie-Slugs der Zielstruktur */
    private function subSlugs(): array
    {
        $slugs = [];
        foreach ($this->spec['tree'] as $main) {
            foreach (array_keys($main['children']) as $slug) {
                $slugs[$slug] = true;
            }
        }

        return $slugs;
    }

    /** @return list<array{category_id: int, slug: string, is_primary: bool}> */
    private function postCategories(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT pc.category_id, c.slug, pc.is_primary FROM post_categories pc JOIN categories c ON c.id = pc.category_id
             WHERE pc.post_id = ? ORDER BY pc.is_primary DESC, c.id'
        );
        $statement->execute([$postId]);

        return array_map(static fn (array $row): array => [
            'category_id' => (int) $row['category_id'], 'slug' => (string) $row['slug'], 'is_primary' => (int) $row['is_primary'] === 1,
        ], $statement->fetchAll());
    }

    /**
     * Die Revisionen, deren Snapshot die Website oder die Freigabe liest: der Arbeitsstand und der freigegebene Stand.
     *
     * @param array<string, mixed> $post
     * @return list<int>
     */
    private function snapshotRevisions(array $post): array
    {
        $revisions = [(int) $post['revision']];
        if ($post['published_revision'] !== null) {
            $revisions[] = (int) $post['published_revision'];
        }

        return array_values(array_unique($revisions));
    }

    /** @param array<string, array<string, mixed>> $bySlug */
    private function isApplied(array $bySlug): bool
    {
        foreach ($this->spec['tree'] as $mainSlug => $main) {
            if (!isset($bySlug[$mainSlug]) || $bySlug[$mainSlug]['parent_id'] !== null || !$bySlug[$mainSlug]['active']) {
                return false;
            }
            foreach (array_keys($main['children']) as $childSlug) {
                if (!isset($bySlug[$childSlug]) || $bySlug[$childSlug]['parent_id'] !== $bySlug[$mainSlug]['id']) {
                    return false;
                }
            }
        }
        foreach (array_keys($this->spec['retire']) as $oldSlug) {
            if (isset($bySlug[$oldSlug]) && $bySlug[$oldSlug]['active']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function buildBackup(array $plan): array
    {
        $snapshots = [];
        $read = $this->pdo->prepare('SELECT JSON_EXTRACT(snapshot, \'$.categories\') FROM post_revisions WHERE post_id = ? AND revision = ?');
        foreach ($plan['posts'] as $post) {
            foreach ($post['revisions'] as $revision) {
                $read->execute([$post['id'], $revision]);
                $value = $read->fetchColumn();
                if ($value !== false && $value !== null) {
                    $snapshots[] = ['post_id' => $post['id'], 'revision' => $revision, 'categories' => json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR)];
                }
            }
        }

        $queueChanged = [];
        foreach ($plan['queue'] as $item) {
            if ($item['action'] === 'set') {
                $queueChanged[] = ['id' => $item['id'], 'category_id' => $this->queueCategoryId($item['id'])];
            }
        }

        $postIds = array_column($plan['posts'], 'id');

        return [
            'version' => self::BACKUP_VERSION,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'categories' => $this->categories->all(true),
            'post_categories' => array_values(array_filter(
                $this->pdo->query('SELECT post_id, category_id, is_primary FROM post_categories ORDER BY post_id, category_id')->fetchAll(),
                static fn (array $row): bool => in_array((int) $row['post_id'], $postIds, true)
            )),
            'post_ids' => array_map('intval', $postIds),
            'snapshots' => $snapshots,
            'queue_changed' => $queueChanged,
            'redirects' => $this->pdo->query('SELECT old_slug, category_id FROM category_redirects')->fetchAll(),
        ];
    }

    private function queueCategoryId(int $queueId): ?int
    {
        $statement = $this->pdo->prepare('SELECT category_id FROM queue_items WHERE id = ?');
        $statement->execute([$queueId]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (int) $value;
    }

    /** @param array<string, mixed> $backup */
    private function writeBackup(array $backup): string
    {
        $directory = (string) $this->config['storage_dir'] . '/backups';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Der Sicherungsordner lässt sich nicht anlegen: ' . $directory);
        }

        $file = $directory . '/category-restructure-' . gmdate('Ymd-His') . '.json';
        $json = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($file, $json) === false || json_decode((string) file_get_contents($file), true) !== json_decode($json, true)) {
            throw new RuntimeException('Die Sicherung ließ sich nicht schreiben oder nicht zurücklesen. Es wurde nichts geändert.');
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, int>
     */
    private function write(array $plan): array
    {
        foreach ($this->categories->all(true) as $category) {
            $this->formerMains[(string) $category['slug']] = $category['parent_id'] === null;
        }
        $ids = $this->writeCategories();
        $redirects = $this->writeRedirects($ids);

        $subs = [];
        foreach ($this->spec['tree'] as $main) {
            foreach ($main['children'] as $slug => $child) {
                $subs[$slug] = $child['name'];
            }
        }

        $posts = 0;
        $snapshots = 0;
        $clear = $this->pdo->prepare('DELETE FROM post_categories WHERE post_id = ?');
        $link = $this->pdo->prepare('INSERT INTO post_categories (post_id, category_id, is_primary) VALUES (?, ?, 1)');
        $patch = $this->pdo->prepare('UPDATE post_revisions SET snapshot = JSON_SET(snapshot, \'$.categories\', CAST(? AS JSON)) WHERE post_id = ? AND revision = ?');
        foreach ($plan['posts'] as $post) {
            $slug = (string) $post['after'];
            $clear->execute([$post['id']]);
            $link->execute([$post['id'], $ids[$slug]]);
            $posts++;

            $json = json_encode([['slug' => $slug, 'name' => $subs[$slug], 'primary' => true]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            foreach ($post['revisions'] as $revision) {
                $patch->execute([$json, $post['id'], $revision]);
                $snapshots += $patch->rowCount();
            }
        }

        $queue = 0;
        $setQueue = $this->pdo->prepare('UPDATE queue_items SET category_id = ? WHERE id = ?');
        foreach ($plan['queue'] as $item) {
            if ($item['action'] === 'set') {
                $setQueue->execute([$ids[$item['after']], $item['id']]);
                $queue++;
            }
        }

        return [
            'categories_created' => count($plan['categories']['create']),
            'categories_updated' => count($plan['categories']['update']),
            'categories_retired' => count($plan['categories']['retire']),
            'redirects' => $redirects,
            'posts' => $posts,
            'snapshots' => $snapshots,
            'queue' => $queue,
        ];
    }

    /** @return array<string, int> Slug → ID aller Zielkategorien */
    private function writeCategories(): array
    {
        $ids = [];
        $position = 0;
        foreach ($this->spec['tree'] as $mainSlug => $main) {
            $position += 10;
            $ids[$mainSlug] = $this->upsert($mainSlug, null, $position, $main);
            $childPosition = 0;
            foreach ($main['children'] as $childSlug => $child) {
                $childPosition += 10;
                $ids[$childSlug] = $this->upsert($childSlug, $ids[$mainSlug], $childPosition, $child + ['interest' => 3]);
            }
        }

        return $ids;
    }

    /**
     * Gibt es den Slug, wird die Zeile umgehängt (Name und Beschreibung der Zielstruktur gelten); sonst neu angelegt.
     *
     * @param array<string, mixed> $definition
     */
    private function upsert(string $slug, ?int $parentId, int $position, array $definition): int
    {
        $fields = [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'parent_id' => $parentId,
            'position' => $position,
            'interest' => $definition['interest'],
            'keywords' => $definition['keywords'],
            'active' => true,
        ];

        $existing = $this->categories->findBySlug($slug);
        if ($existing !== null) {
            $this->categories->update((int) $existing['id'], $fields);

            return (int) $existing['id'];
        }

        return $this->categories->insert(['slug' => $slug, 'avoid' => []] + $fields);
    }

    /**
     * Alte Kategorien abschalten und ihre Adresse auf die neue Kategorie weiterleiten; umgehängte Kategorien,
     * deren Adresse sich ändert (Hauptkategorie → Unterkategorie), leiten auf sich selbst.
     *
     * @param array<string, int> $ids
     */
    private function writeRedirects(array $ids): int
    {
        $upsert = $this->pdo->prepare(
            'INSERT INTO category_redirects (old_slug, category_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE category_id = VALUES(category_id)'
        );

        $count = 0;
        foreach ($this->spec['retire'] as $oldSlug => $targetSlug) {
            $old = $this->categories->findBySlug($oldSlug);
            $this->categories->update((int) $old['id'], ['active' => false]);
            $upsert->execute([$oldSlug, $ids[$targetSlug]]);
            $count++;
        }

        foreach ($this->spec['tree'] as $main) {
            foreach (array_keys($main['children']) as $childSlug) {
                if (!isset($this->spec['retire'][$childSlug]) && $this->wasMainCategory($childSlug)) {
                    $upsert->execute([$childSlug, $ids[$childSlug]]);
                    $count++;
                }
            }
        }

        return $count;
    }

    private function wasMainCategory(string $slug): bool
    {
        return $this->formerMains[$slug] ?? false;
    }

    /** @param array<string, mixed> $plan */
    private function verify(array $plan): void
    {
        $failures = [];

        $orphans = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM categories c JOIN categories p ON p.id = c.parent_id WHERE p.parent_id IS NOT NULL OR p.id = c.id'
        )->fetchColumn();
        if ($orphans > 0) {
            $failures[] = 'Es gibt Kategorien unter einer Unterkategorie oder unter sich selbst.';
        }

        $inactiveParent = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM categories c JOIN categories p ON p.id = c.parent_id WHERE c.active = 1 AND p.active = 0'
        )->fetchColumn();
        if ($inactiveParent > 0) {
            $failures[] = 'Eine aktive Unterkategorie hängt unter einer inaktiven Hauptkategorie.';
        }

        $duplicates = (int) $this->pdo->query('SELECT COUNT(*) FROM (SELECT slug FROM categories GROUP BY slug HAVING COUNT(*) > 1) d')->fetchColumn();
        if ($duplicates > 0) {
            $failures[] = 'Es gibt doppelte Slugs.';
        }

        foreach ($plan['posts'] as $post) {
            $links = $this->postCategories((int) $post['id']);
            if (count($links) !== 1 || $links[0]['slug'] !== $post['after'] || !$links[0]['is_primary']) {
                $failures[] = sprintf('Artikel %d hat nicht genau die eine primäre Zuordnung „%s“.', $post['id'], $post['after']);
            }
        }

        $stale = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM post_categories pc
             JOIN categories c ON c.id = pc.category_id
             JOIN posts p ON p.id = pc.post_id
             WHERE c.active = 0 AND p.status <> 'archived'"
        )->fetchColumn();
        if ($stale > 0) {
            $failures[] = 'Nicht archivierte Artikel hängen noch an abgeschalteten Kategorien.';
        }

        $queueStale = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM queue_items q JOIN categories c ON c.id = q.category_id
             WHERE c.active = 0 AND q.status IN ('open', 'claimed', 'review')"
        )->fetchColumn();
        if ($queueStale > 0) {
            $failures[] = 'Offene Queue-Einträge hängen noch an abgeschalteten Kategorien.';
        }

        $liveWithoutCategory = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM posts p WHERE p.status IN ('published', 'scheduled') AND NOT EXISTS (SELECT 1 FROM post_categories pc WHERE pc.post_id = p.id)"
        )->fetchColumn();
        if ($liveWithoutCategory > 0) {
            $failures[] = 'Freigegebene Artikel ohne Kategorie.';
        }

        if ($failures !== []) {
            throw new DomainError('verification_failed', 'Die Prüfung nach dem Schreiben ist fehlgeschlagen, nichts wurde übernommen: ' . implode(' | ', $failures), ['failures' => $failures]);
        }
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, int>
     */
    private function restore(array $backup): array
    {
        $oldIds = array_map(static fn (array $row): int => (int) $row['id'], $backup['categories']);
        $created = array_values(array_diff(
            array_map('intval', $this->pdo->query('SELECT id FROM categories')->fetchAll(PDO::FETCH_COLUMN)),
            $oldIds
        ));

        $postIds = array_map('intval', $backup['post_ids'] ?? []);
        $this->assertNoLaterUse($created, $postIds, array_column($backup['queue_changed'], 'id'));

        $clear = $this->pdo->prepare('DELETE FROM post_categories WHERE post_id = ?');
        foreach ($postIds as $postId) {
            $clear->execute([$postId]);
        }
        $link = $this->pdo->prepare('INSERT INTO post_categories (post_id, category_id, is_primary) VALUES (?, ?, ?)');
        foreach ($backup['post_categories'] as $row) {
            $link->execute([$row['post_id'], $row['category_id'], $row['is_primary']]);
        }

        $patch = $this->pdo->prepare('UPDATE post_revisions SET snapshot = JSON_SET(snapshot, \'$.categories\', CAST(? AS JSON)) WHERE post_id = ? AND revision = ?');
        foreach ($backup['snapshots'] as $snapshot) {
            $patch->execute([json_encode($snapshot['categories'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $snapshot['post_id'], $snapshot['revision']]);
        }

        $setQueue = $this->pdo->prepare('UPDATE queue_items SET category_id = ? WHERE id = ?');
        foreach ($backup['queue_changed'] as $item) {
            $setQueue->execute([$item['category_id'], $item['id']]);
        }

        $this->pdo->exec('DELETE FROM category_redirects');
        $restoreRedirect = $this->pdo->prepare('INSERT INTO category_redirects (old_slug, category_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())');
        foreach ($backup['redirects'] as $row) {
            $restoreRedirect->execute([$row['old_slug'], $row['category_id']]);
        }

        // Erst alle bestehenden Zeilen unter ihren alten Elternteil zurück, dann die neuen löschen: Kinder vor Eltern.
        foreach ($backup['categories'] as $row) {
            $this->categories->update((int) $row['id'], [
                'slug' => $row['slug'], 'name' => $row['name'], 'description' => $row['description'], 'parent_id' => null,
                'position' => $row['position'], 'interest' => $row['interest'], 'keywords' => $row['keywords'],
                'avoid' => $row['avoid'], 'active' => $row['active'],
            ]);
        }
        foreach ($backup['categories'] as $row) {
            if ($row['parent_id'] !== null) {
                $this->categories->update((int) $row['id'], ['parent_id' => (int) $row['parent_id']]);
            }
        }

        $delete = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $children = $this->pdo->query('SELECT id FROM categories WHERE parent_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_intersect($created, array_map('intval', $children)) as $id) {
            $delete->execute([$id]);
        }
        foreach (array_diff($created, array_map('intval', $children)) as $id) {
            $delete->execute([$id]);
        }

        return ['categories_removed' => count($created), 'posts' => count($postIds), 'snapshots' => count($backup['snapshots']), 'queue' => count($backup['queue_changed'])];
    }

    /**
     * Das Zurücksetzen löscht die neuen Kategorien. Hängt etwas anderes als das Gesicherte daran, bricht es ab.
     *
     * @param list<int> $created
     * @param list<int> $restoredPosts
     * @param list<int> $restoredQueue
     */
    private function assertNoLaterUse(array $created, array $restoredPosts, array $restoredQueue): void
    {
        if ($created === []) {
            return;
        }

        $marks = implode(', ', array_fill(0, count($created), '?'));
        $posts = $this->pdo->prepare('SELECT DISTINCT post_id FROM post_categories WHERE category_id IN (' . $marks . ')');
        $posts->execute($created);
        $foreignPosts = array_diff(array_map('intval', $posts->fetchAll(PDO::FETCH_COLUMN)), $restoredPosts);

        $queue = $this->pdo->prepare('SELECT id FROM queue_items WHERE category_id IN (' . $marks . ')');
        $queue->execute($created);
        $foreignQueue = array_diff(array_map('intval', $queue->fetchAll(PDO::FETCH_COLUMN)), array_map('intval', $restoredQueue));

        if ($foreignPosts !== [] || $foreignQueue !== []) {
            throw new DomainError(
                'rollback_blocked',
                'Seit der Umstellung hängen weitere Artikel oder Queue-Einträge an den neuen Kategorien. Erst umhängen, dann zurücksetzen.',
                ['posts' => array_values($foreignPosts), 'queue' => array_values($foreignQueue)]
            );
        }
    }
}
