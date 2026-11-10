<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Like;
use PDO;

/** Liest die Zugriffsstatistik; geschrieben wird sie erst ab Meilenstein 4. */
final class StatsRepository
{
    private const SUMS = 'SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.engaged_seconds) AS engaged_seconds,
        SUM(d.reads_half) AS reads_half, SUM(d.reads_full) AS reads_full';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{post_id?: int, path_prefix?: string} $filter
     * @return list<array<string, mixed>> key, label, Summen; Tage aufsteigend, sonst meiste Aufrufe zuerst
     */
    public function totalsBy(string $groupBy, string $from, string $to, array $filter): array
    {
        [$keyColumn, $labelColumn, $joins, $order] = match ($groupBy) {
            'day' => ['d.day', 'd.day', '', 'd.day ASC'],
            'post' => ['p.slug', 'p.title', 'JOIN posts p ON p.id = d.post_id', 'views DESC, p.id'],
            'category' => [
                'c.slug', 'c.name',
                'JOIN post_categories pc ON pc.post_id = d.post_id AND pc.is_primary = 1 JOIN categories c ON c.id = pc.category_id',
                'views DESC, c.id',
            ],
        };

        [$conditions, $parameters] = $this->dailyConditions($from, $to, $filter);
        $statement = $this->pdo->prepare(
            "SELECT $keyColumn AS `key`, $labelColumn AS label, " . self::SUMS . "
             FROM stats_daily d $joins WHERE " . implode(' AND ', $conditions) . "
             GROUP BY $keyColumn, $labelColumn ORDER BY $order"
        );
        $statement->execute($parameters);

        return array_map($this->castTotals(...), $statement->fetchAll());
    }

    /**
     * Suchbegriffe, deren Trefferzahl (`extra.hits`) in jeder gezählten Zeile 0 war.
     *
     * @return list<array{value: string, count: int}> häufigste zuerst
     */
    public function searchesWithoutResults(string $from, string $to, int $limit): array
    {
        $statement = $this->pdo->prepare(
            "SELECT value, SUM(count) AS count FROM stats_dimensions
             WHERE dimension = 'search' AND day BETWEEN ? AND ?
             GROUP BY value
             HAVING SUM(JSON_EXTRACT(extra, '$.hits') IS NULL) = 0 AND SUM(CAST(JSON_EXTRACT(extra, '$.hits') AS UNSIGNED)) = 0
             ORDER BY count DESC, value LIMIT " . max(1, $limit)
        );
        $statement->execute([$from, $to]);

        return array_map(static fn (array $row): array => ['value' => (string) $row['value'], 'count' => (int) $row['count']], $statement->fetchAll());
    }

    /**
     * @param array{post_id?: int, path_prefix?: string} $filter
     * @return array<string, int|string>
     */
    public function totals(string $from, string $to, array $filter): array
    {
        [$conditions, $parameters] = $this->dailyConditions($from, $to, $filter);
        $statement = $this->pdo->prepare(
            'SELECT ' . self::SUMS . ' FROM stats_daily d WHERE ' . implode(' AND ', $conditions)
        );
        $statement->execute($parameters);

        return $this->castTotals($statement->fetch());
    }

    /**
     * @param array{post_id?: int, path_prefix?: string} $filter
     * @return list<array{value: string, count: int, hits?: int}> höchstens `$limit`, häufigste zuerst
     */
    public function dimension(string $dimension, string $from, string $to, array $filter, int $limit): array
    {
        $conditions = ['s.dimension = ?', 's.day BETWEEN ? AND ?'];
        $parameters = [$dimension, $from, $to];
        if (isset($filter['post_id'])) {
            $conditions[] = 's.path IN (SELECT path FROM stats_daily WHERE post_id = ?)';
            $parameters[] = $filter['post_id'];
        }
        if (isset($filter['path_prefix'])) {
            $conditions[] = 's.path LIKE ? ' . Like::ESCAPE_CLAUSE;
            $parameters[] = Like::startsWith($filter['path_prefix']);
        }

        $statement = $this->pdo->prepare(
            'SELECT s.value, SUM(s.count) AS count, SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(s.extra, \'$.hits\')) AS UNSIGNED), 0)) AS hits
             FROM stats_dimensions s WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY s.value ORDER BY count DESC, s.value LIMIT ' . max(1, $limit)
        );
        $statement->execute($parameters);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'count' => (int) $row['count'],
            'hits' => (int) $row['hits'],
        ], $statement->fetchAll());
    }

    /**
     * @param array{post_id?: int, path_prefix?: string} $filter
     * @return array{0: list<string>, 1: list<int|string>}
     */
    private function dailyConditions(string $from, string $to, array $filter): array
    {
        $conditions = ['d.day BETWEEN ? AND ?'];
        $parameters = [$from, $to];
        if (isset($filter['post_id'])) {
            $conditions[] = 'd.post_id = ?';
            $parameters[] = $filter['post_id'];
        }
        if (isset($filter['path_prefix'])) {
            $conditions[] = 'd.path LIKE ? ' . Like::ESCAPE_CLAUSE;
            $parameters[] = Like::startsWith($filter['path_prefix']);
        }

        return [$conditions, $parameters];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castTotals(array $row): array
    {
        foreach (['views', 'visitors', 'engaged_seconds', 'reads_half', 'reads_full'] as $column) {
            $row[$column] = (int) $row[$column];
        }
        if (isset($row['key'])) {
            $row['key'] = (string) $row['key'];
            $row['label'] = (string) $row['label'];
        }

        return $row;
    }
}
