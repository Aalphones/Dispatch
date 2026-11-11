<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Liest die Zugriffsstatistik für Agenten und Redaktionsmodus. */
final class StatsService
{
    private const TOTAL_GROUPS = ['day', 'post', 'category'];
    private const DIMENSION_GROUPS = ['referrer', 'device', 'outbound', 'search'];
    private const POST_DIMENSIONS = ['referrer', 'device', 'outbound'];
    private const FILTER_KEYS = ['post', 'path_prefix'];
    private const DEFAULT_DAYS = 30;
    private const MAX_DAYS = 366;
    private const DIMENSION_LIMIT = 50;
    private const REPORT_MAX_DAYS = 365;
    private const TOP_POSTS_LIMIT = 10;
    private const PATH_PREFIX_MAX_LENGTH = 300;

    private StatsRepository $stats;
    private PostRepository $posts;

    public function __construct(PDO $pdo)
    {
        $this->stats = new StatsRepository($pdo);
        $this->posts = new PostRepository($pdo);
    }

    /**
     * @param array<string, mixed> $filter `post` (ID oder Slug) oder `path_prefix`
     * @return array<string, mixed> {from, to, group_by, rows}
     */
    public function stats(?string $from, ?string $to, string $groupBy = 'day', array $filter = []): array
    {
        $groups = [...self::TOTAL_GROUPS, ...self::DIMENSION_GROUPS];
        if (!in_array($groupBy, $groups, true)) {
            throw new DomainError('invalid_value', sprintf('Unbekannte Gruppierung „%s“. Gültig: %s.', $groupBy, implode(', ', $groups)), ['field' => 'group_by']);
        }
        [$from, $to] = $this->period($from, $to);
        $normalizedFilter = $this->normalizeFilter($filter);

        if (in_array($groupBy, self::TOTAL_GROUPS, true)) {
            return ['from' => $from, 'to' => $to, 'group_by' => $groupBy, 'rows' => $this->stats->totalsBy($groupBy, $from, $to, $normalizedFilter)];
        }

        $rows = $this->stats->dimension($groupBy, $from, $to, $normalizedFilter, self::DIMENSION_LIMIT);
        if ($groupBy !== 'search') {
            $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'count' => $row['count']], $rows);
        }

        return ['from' => $from, 'to' => $to, 'group_by' => $groupBy, 'rows' => $rows];
    }

    /**
     * Summen des Artikels plus Aufschlüsselung nach Herkunft, Gerät und ausgehenden Links.
     *
     * @param int|string $idOrSlug Zahl = ID, Text = Slug
     * @return array<string, mixed>
     */
    public function postStats(int|string $idOrSlug, ?string $from, ?string $to): array
    {
        $post = $this->findPost($idOrSlug);
        [$from, $to] = $this->period($from, $to);
        $filter = ['post_id' => (int) $post['id']];

        $result = [
            'post' => ['id' => (int) $post['id'], 'slug' => $post['slug'], 'title' => $post['title']],
            'from' => $from,
            'to' => $to,
            'totals' => $this->stats->totals($from, $to, $filter),
        ];
        foreach (self::POST_DIMENSIONS as $dimension) {
            $rows = $this->stats->dimension($dimension, $from, $to, $filter, self::DIMENSION_LIMIT);
            $result[$dimension] = array_map(static fn (array $row): array => ['value' => $row['value'], 'count' => $row['count']], $rows);
        }

        return $result;
    }

    /**
     * Gesamtbild der letzten `$days` Tage für den Redaktionsmodus.
     *
     * @return array<string, mixed>
     */
    public function report(int $days): array
    {
        [$from, $to] = $this->lastDays($days);
        $totals = $this->stats->totals($from, $to, []);

        return [
            'from' => $from,
            'to' => $to,
            'views' => $totals['views'],
            'visitors' => $totals['visitors'],
            'read_full_pct' => $this->readFullPercent($totals),
            'avg_seconds' => $this->averageSeconds($totals),
            'daily' => $this->dailyViews($from, $to, []),
            'top_posts' => array_map(fn (array $row): array => [
                'id' => $row['id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'views' => $row['views'],
                'read_full_pct' => $this->readFullPercent($row),
            ], $this->stats->topPosts($from, $to, self::TOP_POSTS_LIMIT)),
            'referrers' => $this->valueCounts('referrer', $from, $to),
            'searches_without_hits' => $this->stats->searchesWithoutResults($from, $to, self::DIMENSION_LIMIT),
            'devices' => $this->valueCounts('device', $from, $to),
        ];
    }

    /**
     * Kennzahlen eines Artikels der letzten `$days` Tage.
     *
     * @param int|string $idOrSlug
     * @return array<string, mixed>
     */
    public function postReport(int|string $idOrSlug, int $days): array
    {
        $post = $this->findPost($idOrSlug);
        [$from, $to] = $this->lastDays($days);
        $filter = ['post_id' => (int) $post['id']];
        $totals = $this->stats->totals($from, $to, $filter);

        return [
            'views' => $totals['views'],
            'read_full_pct' => $this->readFullPercent($totals),
            'avg_seconds' => $this->averageSeconds($totals),
            'daily' => $this->dailyViews($from, $to, $filter),
        ];
    }

    /** @return array{0: string, 1: string} die letzten `$days` Tage bis heute, als `Y-m-d` (UTC) */
    private function lastDays(int $days): array
    {
        if ($days < 1 || $days > self::REPORT_MAX_DAYS) {
            throw new DomainError('invalid_value', sprintf('days muss eine ganze Zahl von 1 bis %d sein.', self::REPORT_MAX_DAYS), ['field' => 'days']);
        }
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));

        return [$today->modify('-' . ($days - 1) . ' days')->format('Y-m-d'), $today->format('Y-m-d')];
    }

    /** @param array<string, mixed> $totals views, reads_full */
    private function readFullPercent(array $totals): int
    {
        if ($totals['views'] === 0) {
            return 0;
        }

        return (int) round($totals['reads_full'] / $totals['views'] * 100);
    }

    /** @param array<string, mixed> $totals engaged_seconds, reads_half */
    private function averageSeconds(array $totals): int
    {
        return (int) round($totals['engaged_seconds'] / max($totals['reads_half'], 1));
    }

    /**
     * Jeder Tag des Zeitraums, auch ohne Aufrufe (Diagramm ohne Lücken).
     *
     * @param array{post_id?: int} $filter
     * @return list<array{day: string, views: int}>
     */
    private function dailyViews(string $from, string $to, array $filter): array
    {
        $viewsByDay = [];
        foreach ($this->stats->totalsBy('day', $from, $to, $filter) as $row) {
            $viewsByDay[$row['key']] = $row['views'];
        }

        $daily = [];
        $day = new DateTimeImmutable($from, new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($to, new DateTimeZone('UTC'));
        while ($day <= $end) {
            $key = $day->format('Y-m-d');
            $daily[] = ['day' => $key, 'views' => $viewsByDay[$key] ?? 0];
            $day = $day->modify('+1 day');
        }

        return $daily;
    }

    /** @return list<array{value: string, count: int}> */
    private function valueCounts(string $dimension, string $from, string $to): array
    {
        $rows = $this->stats->dimension($dimension, $from, $to, [], self::DIMENSION_LIMIT);

        return array_map(static fn (array $row): array => ['value' => $row['value'], 'count' => $row['count']], $rows);
    }

    /** @return array{0: string, 1: string} von und bis als `Y-m-d` (UTC) */
    private function period(?string $from, ?string $to): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $end = $to === null ? $today : $this->parseDate('to', $to);
        $start = $from === null ? $end->modify('-' . (self::DEFAULT_DAYS - 1) . ' days') : $this->parseDate('from', $from);

        if ($start > $end) {
            throw new DomainError('invalid_value', 'Das Feld „from“ liegt nach „to“. Der Zeitraum geht von früh nach spät.', ['field' => 'from']);
        }
        $dayCount = (int) $start->diff($end)->days + 1;
        if ($dayCount > self::MAX_DAYS) {
            throw new DomainError('invalid_value', sprintf('Der Zeitraum umfasst %d Tage, erlaubt sind höchstens %d.', $dayCount, self::MAX_DAYS), ['field' => 'from']);
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function parseDate(string $field, string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $isValid = $date !== false && $date->format('Y-m-d') === $value;
        if (!$isValid) {
            throw new DomainError('invalid_value', sprintf('Das Feld „%s“ muss ein Datum der Form JJJJ-MM-TT sein, z. B. 2026-11-10.', $field), ['field' => $field]);
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array{post_id?: int, path_prefix?: string}
     */
    private function normalizeFilter(array $filter): array
    {
        foreach (array_keys($filter) as $key) {
            if (!in_array($key, self::FILTER_KEYS, true)) {
                throw new DomainError('invalid_value', sprintf('Der Filter „%s“ ist unbekannt. Gültig: %s.', (string) $key, implode(', ', self::FILTER_KEYS)), ['field' => 'filter']);
            }
        }

        $normalized = [];
        if (array_key_exists('post', $filter)) {
            $reference = $filter['post'];
            if (!is_int($reference) && !is_string($reference)) {
                throw new DomainError('invalid_value', 'Der Filter „post“ braucht die ID oder den Slug eines Artikels.', ['field' => 'filter']);
            }
            $normalized['post_id'] = (int) $this->findPost($reference)['id'];
        }
        if (array_key_exists('path_prefix', $filter)) {
            $prefix = $filter['path_prefix'];
            if (!is_string($prefix) || $prefix === '' || strlen($prefix) > self::PATH_PREFIX_MAX_LENGTH || !str_starts_with($prefix, '/')) {
                throw new DomainError('invalid_value', 'Der Filter „path_prefix“ muss ein Pfad sein, der mit „/“ beginnt, z. B. „/artikel/“.', ['field' => 'filter']);
            }
            $normalized['path_prefix'] = $prefix;
        }

        return $normalized;
    }

    /**
     * @param int|string $idOrSlug
     * @return array<string, mixed>
     */
    private function findPost(int|string $idOrSlug): array
    {
        $post = is_int($idOrSlug) ? $this->posts->find($idOrSlug) : $this->posts->findBySlug($idOrSlug);
        if ($post === null) {
            throw new DomainError('not_found', sprintf('Den Artikel „%s“ gibt es nicht.', (string) $idOrSlug), ['post' => $idOrSlug]);
        }

        return $post;
    }
}
