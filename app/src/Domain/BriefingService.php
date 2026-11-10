<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Das Briefing, mit dem ein Agent seine Arbeit beginnt. */
final class BriefingService
{
    private const REQUESTED_MODES = ['auto', 'scheduled', 'queue'];
    private const RECENT_POSTS = 30;
    private const REACTION_DAYS = 90;
    private const INTEREST_DAYS = 30;
    private const TOP_POSTS = 10;
    private const TOP_CATEGORIES = 5;
    private const SEARCHES_WITHOUT_RESULTS = 10;
    private const ROTATION_CAP_DAYS = 90;
    private const ROTATION_DAYS_WITHOUT_POST = 60;

    private BriefingRepository $briefing;
    private CategoryRepository $categories;
    private QueueRepository $queue;
    private StatsRepository $stats;
    private DocumentService $documents;
    private SettingService $settings;
    private ReactionService $reactions;

    /** @param array<string, mixed> $config */
    public function __construct(PDO $pdo, array $config)
    {
        $this->briefing = new BriefingRepository($pdo);
        $this->categories = new CategoryRepository($pdo);
        $this->queue = new QueueRepository($pdo);
        $this->stats = new StatsRepository($pdo);
        $this->documents = new DocumentService($pdo, $config);
        $this->settings = new SettingService($pdo, $config);
        $this->reactions = new ReactionService($pdo);
    }

    /**
     * @param string $mode `auto`, `scheduled` oder `queue`
     * @return array<string, mixed>
     */
    public function brief(string $mode): array
    {
        if (!in_array($mode, self::REQUESTED_MODES, true)) {
            throw new DomainError('invalid_value', sprintf('Der Modus „%s“ ist unbekannt. Gültig: %s.', $mode, implode(', ', self::REQUESTED_MODES)), ['field' => 'mode']);
        }

        $returned = $this->briefing->returnedDrafts();
        $nextItem = $this->queue->nextClaimable();
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));

        return [
            'mode' => $mode === 'auto' ? $this->resolveAutoMode($returned !== [], $nextItem !== null) : $mode,
            'requested_mode' => $mode,
            'today' => $today->format('Y-m-d'),
            'guidelines' => $this->documents->get('editorial_guidelines')['content'],
            'preferences' => $this->documents->get('preferences')['content'],
            'form_rules' => $this->settings->formRules(),
            'categories' => $this->categoryOverview(),
            'recent_posts' => $this->briefing->recentPosts(self::RECENT_POSTS),
            'reader_interest' => $this->readerInterest($today),
            'reactions' => $this->reactions->recent(self::REACTION_DAYS),
            'queue' => [
                'counts' => $this->queue->countByEffectiveStatus(),
                'next' => $nextItem === null ? null : [
                    'id' => $nextItem['id'],
                    'title' => $nextItem['title'],
                    'priority' => $nextItem['priority'],
                    'depth' => $nextItem['depth'],
                ],
            ],
            'returned' => $returned,
            'workflow' => '',
        ];
    }

    /**
     * @return array<int, array{post_count: int, last_published_at: string|null, rotation: int}> Schlüssel = Kategorie-ID
     */
    public function categoryStats(): array
    {
        $result = [];
        foreach ($this->briefing->liveStatsByCategory() as $categoryId => $row) {
            $result[$categoryId] = [
                'post_count' => $row['post_count'],
                'last_published_at' => $row['last_published_at'],
                'rotation' => $this->rotation($row['interest'], $row['days_since_last']),
            ];
        }

        return $result;
    }

    private function resolveAutoMode(bool $hasReturned, bool $hasOpenItem): string
    {
        if ($hasReturned) {
            return 'returned';
        }

        return $hasOpenItem ? 'queue' : 'scheduled';
    }

    /** Interesse mal Tage seit dem letzten Live-Artikel (höchstens 90); Interesse 0 schließt die Kategorie aus. */
    private function rotation(int $interest, ?int $daysSinceLast): int
    {
        if ($interest === 0) {
            return 0;
        }

        return $interest * min($daysSinceLast ?? self::ROTATION_DAYS_WITHOUT_POST, self::ROTATION_CAP_DAYS);
    }

    /** @return list<array<string, mixed>> aktive Kategorien, höchste Rotation zuerst */
    private function categoryOverview(): array
    {
        $stats = $this->categoryStats();
        $all = $this->categories->all(true);
        $slugById = array_column($all, 'slug', 'id');

        $overview = [];
        foreach ($all as $category) {
            if (!$category['active']) {
                continue;
            }
            $categoryStats = $stats[(int) $category['id']];
            $interest = (int) $category['interest'];
            $overview[] = [
                'slug' => $category['slug'],
                'name' => $category['name'],
                'parent' => $category['parent_id'] === null ? null : ($slugById[$category['parent_id']] ?? null),
                'interest' => $interest,
                'keywords' => $category['keywords'],
                'avoid' => $category['avoid'],
                'post_count' => $categoryStats['post_count'],
                'last_published_at' => $categoryStats['last_published_at'],
                'rotation' => $categoryStats['rotation'],
                'excluded' => $interest === 0,
            ];
        }
        usort($overview, static fn (array $first, array $second): int => $second['rotation'] <=> $first['rotation']);

        return $overview;
    }

    /** @return array<string, mixed> */
    private function readerInterest(DateTimeImmutable $today): array
    {
        $from = $today->modify('-' . (self::INTEREST_DAYS - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');

        return [
            'from' => $from,
            'to' => $to,
            'top_posts' => array_slice($this->stats->totalsBy('post', $from, $to, []), 0, self::TOP_POSTS),
            'top_categories' => array_slice($this->stats->totalsBy('category', $from, $to, []), 0, self::TOP_CATEGORIES),
            'searches_without_results' => $this->stats->searchesWithoutResults($from, $to, self::SEARCHES_WITHOUT_RESULTS),
        ];
    }
}
