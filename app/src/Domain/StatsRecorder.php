<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use PDO;

/**
 * Zählt die Beacons des Browsers (ADR-022). Ein Beacon ist ein Ereignis (`view`, `read`, `out`, `search`);
 * jedes zählt genau einmal je Pfad. Verwerfen heißt: stillschweigend nichts zählen.
 */
final class StatsRecorder
{
    private const HITS_PER_HOUR = 60;
    private const MAX_SECONDS = 3600;
    private const MAX_VALUE_LENGTH = 100;
    private const SALT_BYTES = 32;
    private const DEVICES = ['mobil', 'tablet', 'desktop'];
    private const SEARCH_PATH = '/suche/';
    /** Auch Zeichen oberhalb von ASCII (Umlaute, Prozent-Kodierung); keine Steuerzeichen, kein Query, kein Fragment. */
    private const PATH_PATTERN = '#^/[^\x00-\x20\x7f?\#]*$#';
    private const ARTICLE_PATTERN = '#^/artikel/([^/]+)/$#';
    private const HOST_PATTERN = '/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/';

    private readonly StatsRepository $stats;

    /** @param array<string, mixed> $config */
    public function __construct(PDO $pdo, private readonly array $config)
    {
        $this->stats = new StatsRepository($pdo);
    }

    /** @param array<string, mixed> $beacon */
    public function record(array $beacon, string $ip, string $userAgent): void
    {
        $type = $beacon['t'] ?? null;
        $path = $this->validPath($beacon['p'] ?? null);
        if (!is_string($type) || $path === null) {
            return;
        }

        $now = time();
        $day = gmdate('Y-m-d', $now);
        $visitorHash = hash('sha256', $ip . '|' . $userAgent . '|' . $this->daySalt($day));
        $hits = $this->stats->countHourlyHit($day, $visitorHash, gmdate('Y-m-d H:i:s', $now), gmdate('Y-m-d H:i:s', $now - 3600));
        if ($hits > self::HITS_PER_HOUR) {
            return;
        }

        match ($type) {
            'view' => $this->recordView($beacon, $day, $visitorHash, $path),
            'read' => $this->recordRead($beacon, $day, $path),
            'out' => $this->recordOutbound($beacon, $day, $path),
            'search' => $this->recordSearch($beacon, $day),
            default => null,
        };
    }

    /** @param array<string, mixed> $beacon */
    private function recordView(array $beacon, string $day, string $visitorHash, string $path): void
    {
        $isNewVisitor = $this->stats->markVisitorPath($day, $visitorHash, $path);
        $this->stats->addView($day, $path, $this->postIdOf($path), $isNewVisitor);

        $referrer = $this->host($beacon['r'] ?? '');
        if ($referrer === '') {
            $this->stats->addDimension($day, $path, 'referrer', 'direkt', null);
        } elseif ($referrer !== null && !$this->isOwnHost($referrer)) {
            $this->stats->addDimension($day, $path, 'referrer', $referrer, null);
        }

        $campaign = $beacon['u'] ?? '';
        if (is_string($campaign) && trim($campaign) !== '') {
            $this->stats->addDimension($day, $path, 'utm', mb_substr(trim($campaign), 0, self::MAX_VALUE_LENGTH), null);
        }

        $device = $beacon['d'] ?? null;
        if (in_array($device, self::DEVICES, true)) {
            $this->stats->addDimension($day, $path, 'device', $device, null);
        }
    }

    /** @param array<string, mixed> $beacon */
    private function recordRead(array $beacon, string $day, string $path): void
    {
        $seconds = $beacon['s'] ?? null;
        $depth = $beacon['h'] ?? null;
        if (!is_int($seconds) || $seconds < 0 || $seconds > self::MAX_SECONDS || !is_int($depth)) {
            return;
        }

        $this->stats->addRead($day, $path, $this->postIdOf($path), $seconds, $depth >= 50, $depth >= 100);
    }

    /** @param array<string, mixed> $beacon */
    private function recordOutbound(array $beacon, string $day, string $path): void
    {
        $target = $this->host($beacon['z'] ?? null);
        if ($target === null || $target === '' || $this->isOwnHost($target)) {
            return;
        }

        $this->stats->addDimension($day, $path, 'outbound', $target, null);
    }

    /** @param array<string, mixed> $beacon */
    private function recordSearch(array $beacon, string $day): void
    {
        $term = $beacon['q'] ?? null;
        $hits = $beacon['n'] ?? null;
        if (!is_string($term) || !is_int($hits) || $hits < 0) {
            return;
        }

        $term = mb_strtolower(trim($term));
        $length = mb_strlen($term);
        if ($length < 2 || $length > self::MAX_VALUE_LENGTH) {
            return;
        }

        $this->stats->addDimension($day, self::SEARCH_PATH, 'search', $term, min($hits, 100000));
    }

    private function validPath(mixed $path): ?string
    {
        if (!is_string($path) || strlen($path) > 300 || preg_match(self::PATH_PATTERN, $path) !== 1) {
            return null;
        }
        foreach (['/vorschau/', '/anmelden/', '/dispatch'] as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return null;
            }
        }

        return $path;
    }

    private function postIdOf(string $path): ?int
    {
        if (preg_match(self::ARTICLE_PATTERN, $path, $match) !== 1) {
            return null;
        }

        return $this->stats->livePostId(rawurldecode($match[1]));
    }

    /** @return string|null '' für leer, null für ungültig, sonst der Host in Kleinbuchstaben */
    private function host(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $host = strtolower($value);
        if (strlen($host) > self::MAX_VALUE_LENGTH || preg_match(self::HOST_PATTERN, $host) !== 1) {
            return null;
        }

        return $host;
    }

    private function isOwnHost(string $host): bool
    {
        $own = parse_url((string) $this->config['base_url'], PHP_URL_HOST);
        if (!is_string($own)) {
            return false;
        }

        return preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', strtolower($own));
    }

    /**
     * Der Tages-Salt liegt in einer Datei statt in der Datenbank: ein Lesen je Aufruf, und der Wechsel des Tages
     * löscht ohne Cron die Besucher-Tabellen von gestern. Der alte Salt ist danach weg.
     */
    private function daySalt(string $day): string
    {
        $directory = (string) $this->config['storage_dir'] . '/stats';
        $file = $directory . '/salt.json';

        $salt = $this->parseSalt(is_file($file) ? file_get_contents($file) : false, $day);
        if ($salt !== null) {
            return $salt;
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new DomainError('stats_unavailable', 'Der Tages-Salt lässt sich nicht schreiben.');
        }

        try {
            flock($handle, LOCK_EX);
            // Eine parallele Anfrage kann den Salt inzwischen erneuert haben. Gelesen wird über das Handle:
            // unter Windows sperrt flock die Datei für jeden anderen Lesezugriff.
            $salt = $this->parseSalt(stream_get_contents($handle), $day);
            if ($salt === null) {
                $salt = bin2hex(random_bytes(self::SALT_BYTES));
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, json_encode(['day' => $day, 'salt' => $salt], JSON_THROW_ON_ERROR));
                fflush($handle);
                $this->stats->purgeVisitorsBefore($day);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $salt;
    }

    private function parseSalt(string|false $content, string $day): ?string
    {
        $data = is_string($content) ? json_decode($content, true) : null;
        if (!is_array($data) || ($data['day'] ?? null) !== $day || !is_string($data['salt'] ?? null)) {
            return null;
        }

        return $data['salt'];
    }
}
