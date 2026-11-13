<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Veröffentlichungsplan: Je Berliner Kalendertag gibt es bis zu `release_per_day` feste Termine (08:00 bis 18:00, fest
 * verdrahtet in SLOT_MINUTES). Die Warteschlange belegt sie der Reihe nach mit der ältesten Freigabe zuerst; ein
 * verstrichener, ungenutzter Termin verfällt, Termine vergangener Tage werden nie nachgeholt. Feste Termine kommen
 * zusätzlich und erscheinen beim ersten Auslöser ab ihrem Zeitpunkt (ADR-028).
 */
final class ScheduleService
{
    public const ZONE = 'Europe/Berlin';

    private const CHECK_CAP_SECONDS = 900;
    private const MAX_DAYS_AHEAD = 365;
    private const SQL_FORMAT = 'Y-m-d H:i:s';

    /** Minuten nach Mitternacht, je Anzahl Termine pro Tag: 08:00 + round(k · 720 / n / 15) · 15 Minuten. */
    private const SLOT_MINUTES = [
        1 => [480],
        2 => [480, 840],
        3 => [480, 720, 960],
        4 => [480, 660, 840, 1020],
        5 => [480, 630, 765, 915, 1050],
        6 => [480, 600, 720, 840, 960, 1080],
    ];

    private ScheduleRepository $schedule;
    private PostService $posts;
    private SettingService $settings;

    /** @param array<string, mixed> $config */
    public function __construct(PDO $pdo, array $config)
    {
        $this->schedule = new ScheduleRepository($pdo);
        $this->posts = new PostService($pdo, $config);
        $this->settings = new SettingService($pdo, $config);
    }

    /** @return list<string> Uhrzeiten `HH:MM` der Tagestermine */
    public static function slotLabels(int $perDay): array
    {
        return array_map(
            static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60),
            self::SLOT_MINUTES[$perDay]
        );
    }

    public function perDay(): int
    {
        return (int) $this->settings->get('release_per_day');
    }

    /**
     * Liest `YYYY-MM-DDTHH:MM` als Wandzeit in Europe/Berlin und gibt den Zeitpunkt in UTC zurück.
     *
     * @throws DomainError `invalid_value`, wenn das Format falsch ist oder der Zeitpunkt nicht in der Zukunft liegt
     */
    public static function parseAt(?string $at, DateTimeImmutable $nowUtc): DateTimeImmutable
    {
        $format = 'Y-m-d\TH:i';
        $local = $at !== null && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $at) === 1
            ? DateTimeImmutable::createFromFormat($format . '|', $at, new DateTimeZone(self::ZONE))
            : false;
        // Der Rundlauf fängt Überläufe wie den 31. Februar und die ausgefallene Stunde der Zeitumstellung.
        if ($local === false || $local->format($format) !== $at) {
            throw new DomainError('invalid_value', 'at: muss ein gültiges Datum mit Uhrzeit im Format JJJJ-MM-TTTHH:MM sein', ['field' => 'at']);
        }

        $utc = $local->setTimezone(new DateTimeZone('UTC'));
        if ($utc <= $nowUtc) {
            throw new DomainError('invalid_value', 'at: liegt in der Vergangenheit', ['field' => 'at']);
        }
        if ($utc > $nowUtc->modify('+' . self::MAX_DAYS_AHEAD . ' days')) {
            throw new DomainError('invalid_value', 'at: höchstens ein Jahr im Voraus', ['field' => 'at']);
        }

        return $utc;
    }

    /**
     * Die Erscheinungstermine eines Berliner Kalendertags in UTC. Je Tag in Berlin gerechnet, nicht mit festen
     * UTC-Versätzen, damit die Zeitumstellung stimmt.
     *
     * @param DateTimeImmutable $berlinDay Mitternacht des Tages in Europe/Berlin
     * @return list<DateTimeImmutable>
     */
    public function slotsOn(DateTimeImmutable $berlinDay): array
    {
        $utc = new DateTimeZone('UTC');

        return array_map(
            static fn (int $minutes): DateTimeImmutable => $berlinDay->setTime(intdiv($minutes, 60), $minutes % 60)->setTimezone($utc),
            self::SLOT_MINUTES[$this->perDay()]
        );
    }

    /**
     * Der Plan in der Form von `GET /dispatch/api/schedule`: wartende Artikel mit erwartetem Erscheinen, der nächste
     * freie Termin und die heute erschienenen Artikel. Alle Zeiten UTC.
     *
     * @return array{per_day: int, times: list<string>, next_free: string|null, items: list<array<string, mixed>>, released_today: list<array<string, mixed>>}
     */
    public function plan(DateTimeImmutable $nowUtc): array
    {
        $perDay = $this->perDay();
        $today = $this->dayStart($nowUtc);
        $now = $nowUtc->format(self::SQL_FORMAT);
        $dayFrom = $today->setTimezone(new DateTimeZone('UTC'))->format(self::SQL_FORMAT);
        $dayTo = $today->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format(self::SQL_FORMAT);

        $items = $this->queueItems($today, $now, $this->schedule->takenSlots($dayFrom, $dayTo), $nextFree);
        foreach ($this->schedule->waitingFixed() as $post) {
            $items[] = [
                'id' => $post['id'], 'title' => $post['title'], 'slug' => $post['slug'], 'mode' => 'fixed',
                'expected_at' => $post['scheduled_at'], 'position' => null, 'due' => $post['scheduled_at'] <= $now,
            ];
        }
        usort($items, static fn (array $left, array $right): int => [$left['expected_at'], $left['id']] <=> [$right['expected_at'], $right['id']]);

        return [
            'per_day' => $perDay,
            'times' => self::slotLabels($perDay),
            'next_free' => $nextFree,
            'items' => $items,
            'released_today' => $this->schedule->releasedBetween($dayFrom, $dayTo),
        ];
    }

    /**
     * Veröffentlicht, was fällig ist: feste Termine ab ihrem Zeitpunkt, aus der Warteschlange je verstrichenem, freiem
     * Tagestermin von heute höchstens einen Artikel. Läuft schon ein anderer Auslöser, passiert hier nichts.
     *
     * @return int Anzahl veröffentlichter Artikel
     */
    public function releaseDue(DateTimeImmutable $nowUtc): int
    {
        if (!$this->schedule->lock()) {
            return 0;
        }

        try {
            return $this->releaseFixed($nowUtc) + $this->releaseQueue($nowUtc);
        } finally {
            $this->schedule->unlock();
        }
    }

    /** @return int Unix-Sekunden, spätestens in 15 Minuten, früher bei einem näheren Termin */
    public function nextCheck(DateTimeImmutable $nowUtc): int
    {
        $next = $nowUtc->getTimestamp() + self::CHECK_CAP_SECONDS;
        foreach ($this->plan($nowUtc)['items'] as $item) {
            $next = min($next, (new DateTimeImmutable($item['expected_at'], new DateTimeZone('UTC')))->getTimestamp());
        }

        return $next;
    }

    /**
     * Ordnet die Warteschlange den Terminen ab heute zu. Verstrichene Termine von heute mit einem Artikel, der schon
     * bereitstand, ergeben einen fälligen Eintrag; der erste freie künftige Termin ist `$nextFree`.
     *
     * @param list<string> $takenToday
     * @return list<array<string, mixed>>
     */
    private function queueItems(DateTimeImmutable $today, string $now, array $takenToday, ?string &$nextFree): array
    {
        $waiting = $this->schedule->waitingSlots();
        $items = [];
        $nextFree = null;
        $queueIndex = 0;

        for ($dayOffset = 0; $dayOffset < self::MAX_DAYS_AHEAD; $dayOffset++) {
            foreach ($this->slotsOn($today->modify('+' . $dayOffset . ' day')) as $slot) {
                $slotUtc = $slot->format(self::SQL_FORMAT);
                if ($dayOffset === 0 && in_array($slotUtc, $takenToday, true)) {
                    continue;
                }

                $hasWaiting = $queueIndex < count($waiting);
                if ($slotUtc <= $now) {
                    // Ein verstrichener Termin ist nie frei; er ist fällig oder verfallen.
                    if ($hasWaiting && $waiting[$queueIndex]['scheduled_since'] <= $slotUtc) {
                        $items[] = $this->queueItem($waiting[$queueIndex], $slotUtc, $queueIndex, true);
                        $queueIndex++;
                    }
                    continue;
                }
                if (!$hasWaiting) {
                    $nextFree = $slotUtc;

                    return $items;
                }
                $items[] = $this->queueItem($waiting[$queueIndex], $slotUtc, $queueIndex, false);
                $queueIndex++;
            }
        }

        return $items;
    }

    /**
     * @param array{id: int, title: string, slug: string, scheduled_since: string} $post
     * @return array<string, mixed>
     */
    private function queueItem(array $post, string $slotUtc, int $queueIndex, bool $isDue): array
    {
        return [
            'id' => $post['id'], 'title' => $post['title'], 'slug' => $post['slug'], 'mode' => 'slot',
            'expected_at' => $slotUtc, 'position' => $queueIndex + 1, 'due' => $isDue,
        ];
    }

    private function releaseFixed(DateTimeImmutable $nowUtc): int
    {
        $now = $nowUtc->format(self::SQL_FORMAT);
        $released = 0;
        foreach ($this->schedule->waitingFixed() as $post) {
            if ($post['scheduled_at'] <= $now && $this->posts->release($post['id'], null, Actor::schedule())) {
                $released++;
            }
        }

        return $released;
    }

    private function releaseQueue(DateTimeImmutable $nowUtc): int
    {
        $now = $nowUtc->format(self::SQL_FORMAT);
        $today = $this->dayStart($nowUtc);
        $utc = new DateTimeZone('UTC');
        $taken = $this->schedule->takenSlots(
            $today->setTimezone($utc)->format(self::SQL_FORMAT),
            $today->modify('+1 day')->setTimezone($utc)->format(self::SQL_FORMAT)
        );
        $waiting = $this->schedule->waitingSlots();
        $queueIndex = 0;
        $released = 0;

        foreach ($this->slotsOn($today) as $slot) {
            $slotUtc = $slot->format(self::SQL_FORMAT);
            if ($slotUtc > $now || $queueIndex >= count($waiting)) {
                break;
            }
            if (in_array($slotUtc, $taken, true) || $waiting[$queueIndex]['scheduled_since'] > $slotUtc) {
                continue;
            }

            if ($this->posts->release($waiting[$queueIndex]['id'], $slotUtc, Actor::schedule())) {
                $released++;
            }
            $queueIndex++;
        }

        return $released;
    }

    /** Mitternacht des Berliner Kalendertags, in dem `$utc` liegt (bleibt in Europe/Berlin). */
    private function dayStart(DateTimeImmutable $utc): DateTimeImmutable
    {
        return $utc->setTimezone(new DateTimeZone(self::ZONE))->setTime(0, 0);
    }
}
