<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

final class DocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT doc_key, title, content, public_slug, revision, updated_at FROM documents WHERE doc_key = ?'
        );
        $statement->execute([$key]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        $row['revision'] = (int) $row['revision'];

        return $row;
    }

    /** @return list<array<string, mixed>> ohne Inhalt */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT doc_key, title, public_slug, revision, updated_at FROM documents ORDER BY doc_key'
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['revision'] = (int) $row['revision'];
        }

        return $rows;
    }

    public function insert(string $key, string $title, string $content, ?string $publicSlug): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO documents (doc_key, title, content, public_slug, revision, updated_at)
             VALUES (?, ?, ?, ?, 1, UTC_TIMESTAMP())'
        );
        $statement->execute([$key, $title, $content, $publicSlug]);
    }

    /** @return int die neue Revision */
    public function updateContent(string $key, string $title, string $content): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE documents SET title = ?, content = ?, revision = revision + 1, updated_at = UTC_TIMESTAMP() WHERE doc_key = ?'
        );
        $statement->execute([$title, $content, $key]);

        $revision = $this->pdo->prepare('SELECT revision FROM documents WHERE doc_key = ?');
        $revision->execute([$key]);

        return (int) $revision->fetchColumn();
    }

    public function insertRevision(string $key, int $revision, string $content, ?string $note, string $actor): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_revisions (doc_key, revision, content, note, actor, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
        );
        $statement->execute([$key, $revision, $content, $note, $actor]);
    }

    /** @return list<array<string, mixed>> neueste zuerst, mit Inhalt */
    public function revisions(string $key): array
    {
        $statement = $this->pdo->prepare(
            'SELECT revision, content, note, actor, created_at FROM document_revisions WHERE doc_key = ? ORDER BY revision DESC'
        );
        $statement->execute([$key]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['revision'] = (int) $row['revision'];
        }

        return $rows;
    }
}
