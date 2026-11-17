<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

/** Abfragen für den Veröffentlichungsplan; alle Zeiten sind UTC-Strings `Y-m-d H:i:s`. */
final class ScheduleRepository
{
    private const LOCK_NAME = 'dispatch_schedule_release';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, title: string, slug: string, scheduled_since: string, main: string|null}> Warteschlange in gespeicherter Reihenfolge; `main` = Hauptkategorie */
    public function waitingSlots(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, title, slug, scheduled_since, ' . QuotaService::mainOfPostSql('posts.id') . " AS main FROM posts
             WHERE status = 'scheduled' AND schedule_mode = 'slot' ORDER BY schedule_order, id"
        )->fetchAll();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id']] + $row, $rows);
    }

    /** @return list<int> Alle wartenden Queue-Termine, gesperrt fÃ¼r eine atomare Sortierung. */
    public function waitingSlotIdsForUpdate(): array
    {
        $rows = $this->pdo->query(
            "SELECT id FROM posts WHERE status = 'scheduled' AND schedule_mode = 'slot' ORDER BY schedule_order, id FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    public function updateScheduleOrder(int $id, int $position): void
    {
        $statement = $this->pdo->prepare('UPDATE posts SET schedule_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $statement->execute([$position, $id]);
    }

    public function nextSlotOrder(): int
    {
        return (int) $this->pdo->query(
            "SELECT COALESCE(MAX(schedule_order), 0) + 1 FROM posts WHERE status = 'scheduled' AND schedule_mode = 'slot'"
        )->fetchColumn();
    }

    /** @return list<array{id: int, title: string, slug: string, scheduled_at: string}> feste Termine, frühester zuerst */
    public function waitingFixed(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, title, slug, scheduled_at FROM posts
             WHERE status = 'scheduled' AND schedule_mode = 'fixed' ORDER BY scheduled_at, id"
        )->fetchAll();

        return array_map(static fn (array $row): array => ['id' => (int) $row['id']] + $row, $rows);
    }

    /** @return list<string> belegte Erscheinungstermine im Bereich [from, to) */
    public function takenSlots(string $fromUtc, string $toUtc): array
    {
        $statement = $this->pdo->prepare('SELECT release_slot FROM posts WHERE release_slot >= ? AND release_slot < ?');
        $statement->execute([$fromUtc, $toUtc]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array{id: int, title: string, slug: string, published_at: string, mode: string|null}> */
    public function releasedBetween(string $fromUtc, string $toUtc): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, published_at, schedule_mode AS mode FROM posts
             WHERE published_revision IS NOT NULL AND published_at >= ? AND published_at < ? ORDER BY published_at, id'
        );
        $statement->execute([$fromUtc, $toUtc]);

        return array_map(static fn (array $row): array => ['id' => (int) $row['id']] + $row, $statement->fetchAll());
    }

    /** Nicht wartend: Läuft schon ein Auslöser, kann dieser sich sparen, dasselbe zu tun. */
    public function lock(): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([self::LOCK_NAME]);

        return (int) $statement->fetchColumn() === 1;
    }

    public function unlock(): void
    {
        $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([self::LOCK_NAME]);
    }
}
