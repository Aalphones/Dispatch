<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use Dispatch\Util\HttpClient;
use Dispatch\Util\ImageProcessor;
use PDO;

final class MediaService
{
    /** MIME → [kind, ext]; GIF wird als PNG neu kodiert (erstes Bild). */
    private const TYPES = [
        'image/jpeg' => ['image', 'jpg'],
        'image/png' => ['image', 'png'],
        'image/webp' => ['image', 'webp'],
        'image/gif' => ['image', 'png'],
        'video/mp4' => ['video', 'mp4'],
        'video/webm' => ['video', 'webm'],
        'audio/mpeg' => ['audio', 'mp3'],
        'audio/mp4' => ['audio', 'm4a'],
        'audio/ogg' => ['audio', 'ogg'],
        'audio/webm' => ['audio', 'weba'],
        'application/pdf' => ['document', 'pdf'],
    ];

    /** Metadaten-Feld → Höchstlänge. */
    private const META_LENGTH = [
        'alt' => 500,
        'caption' => 1000,
        'credit' => 300,
        'license' => 120,
        'license_url' => 500,
        'source_url' => 1000,
    ];

    private const URL_FIELDS = ['license_url', 'source_url'];

    private const KINDS = ['image', 'video', 'audio', 'document'];

    private MediaRepository $media;
    private ChangeService $changes;
    private AuditRepository $audit;
    private SettingService $settings;
    private ImageProcessor $images;
    private HttpClient $http;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->media = new MediaRepository($pdo);
        $this->changes = new ChangeService($pdo, $config);
        $this->audit = new AuditRepository($pdo);
        $this->settings = new SettingService($pdo, $config);
        $this->images = new ImageProcessor((array) $config['media']);
        $this->http = new HttpClient((array) $config['http'], (string) $config['base_url']);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed> Datensatz plus `duplicate`
     */
    public function store(string $bytes, array $meta, string $origin, Actor $actor): array
    {
        $maxBytes = (int) $this->mediaConfig('max_bytes');
        if (strlen($bytes) > $maxBytes) {
            throw new DomainError('too_large', sprintf('Die Datei ist größer als %d MB.', intdiv($maxBytes, 1024 * 1024)), ['max_bytes' => $maxBytes]);
        }

        [$mime, $kind, $ext] = $this->detectType($bytes);
        $fields = $this->validateMeta($meta);

        $sha = hash('sha256', $bytes);
        $existing = $this->media->findBySha($sha);
        if ($existing !== null) {
            return $existing + ['duplicate' => true];
        }

        if ($origin === 'owner') {
            $fields['credit'] ??= (string) $this->settings->get('author_name') ?: null;
            $fields['license'] ??= 'Eigenes Material';
        }

        $uid = 'm' . bin2hex(random_bytes(5));
        $mediaDir = $this->mediaRoot() . '/' . $uid;
        $tempDir = $this->writeToTempDir($bytes, $mime, $kind, $ext, $result);

        try {
            rename($tempDir, $mediaDir);
            $record = Transaction::join($this->pdo, function () use ($uid, $kind, $mime, $ext, $sha, $mediaDir, $result, $fields, $origin, $actor): array {
                $this->media->insert([
                    'uid' => $uid,
                    'kind' => $kind,
                    'mime' => $ext === 'png' ? 'image/png' : $mime,
                    'ext' => $result['ext'] ?? $ext,
                    'bytes' => (int) filesize($mediaDir . '/original.' . ($result['ext'] ?? $ext)),
                    'sha256' => $sha,
                    'width' => $result['width'] ?? null,
                    'height' => $result['height'] ?? null,
                    'variants' => $result['variants'] ?? [],
                    'alt' => $fields['alt'] ?? null,
                    'caption' => $fields['caption'] ?? null,
                    'credit' => $fields['credit'] ?? null,
                    'license' => $fields['license'] ?? null,
                    'license_url' => $fields['license_url'] ?? null,
                    'source_url' => $fields['source_url'] ?? null,
                    'origin' => $origin,
                ]);
                $this->audit->record($actor, 'media.store', 'media:' . $uid, ['kind' => $kind, 'origin' => $origin, 'sha256' => $sha]);

                return $this->get($uid);
            });
        } catch (\Throwable $error) {
            $this->removeDir($tempDir);
            $this->removeDir($mediaDir);

            throw $error;
        }

        return $record + ['duplicate' => false];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function importFromUrl(string $url, array $meta, Actor $actor): array
    {
        $response = $this->http->get($url, (int) $this->mediaConfig('max_bytes'));

        return $this->store($response->body, $meta, 'import', $actor);
    }

    /** @return array<string, mixed> */
    public function get(string $uid): array
    {
        $record = $this->media->findByUid($uid);
        if ($record === null) {
            throw new DomainError('not_found', sprintf('Das Medium „%s“ gibt es nicht.', $uid), ['uid' => $uid]);
        }

        return $record;
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $kind = null, int $limit = 50, int $offset = 0): array
    {
        if ($kind !== null && !in_array($kind, self::KINDS, true)) {
            throw new DomainError('invalid_value', sprintf('Unbekannte Art „%s“. Gültig: %s.', $kind, implode(', ', self::KINDS)), ['field' => 'kind']);
        }

        return $this->media->list($kind, $limit, $offset);
    }

    /**
     * Öffentlich genutzte Medien ändert nur der Inhaber direkt; sonst entsteht ein Vorschlag.
     *
     * @param array<string, mixed> $meta
     * @return array{applied: list<string>, change_id: int|null}
     */
    public function updateMeta(string $uid, array $meta, Actor $actor): array
    {
        $this->get($uid);
        $fields = $this->validateMeta($meta);

        return Transaction::join($this->pdo, function () use ($uid, $fields, $actor): array {
            if (!$actor->isOwner() && $this->media->isLive($uid)) {
                return ['applied' => [], 'change_id' => $this->changes->propose('media', $uid, $fields, $actor)];
            }

            $this->writeMeta($uid, $fields, $actor);

            return ['applied' => array_keys($fields), 'change_id' => null];
        });
    }

    /**
     * Übernahme eines Vorschlags; läuft in der Transaktion von ChangeService::apply.
     *
     * @param array<string, mixed> $payload
     */
    public function applyChange(string $uid, array $payload): void
    {
        $this->get($uid);
        $this->writeMeta($uid, $this->validateMeta($payload), Actor::owner());
    }

    /** Nur Dateien, die das Medium kennt: `original.<ext>` und die Namen aus `variants`. */
    public function filePath(string $uid, string $file): string
    {
        $record = $this->get($uid);

        $known = ['original.' . $record['ext']];
        foreach ($record['variants'] as $variant) {
            array_push($known, ...$variant['files']);
        }
        if (!in_array($file, $known, true)) {
            throw new DomainError('not_found', sprintf('Die Datei „%s“ gehört nicht zum Medium „%s“.', $file, $uid), ['uid' => $uid]);
        }

        return $this->mediaRoot() . '/' . $uid . '/' . $file;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function detectType(string $bytes): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if ($mime === false || !isset(self::TYPES[$mime])) {
            throw new DomainError(
                'type_rejected',
                sprintf('Der Dateityp „%s“ ist nicht erlaubt. Erlaubt: JPEG, PNG, WebP, GIF, MP4, WebM, MP3, M4A, OGG, PDF.', $mime === false ? 'unbekannt' : $mime),
                ['detected' => $mime === false ? null : $mime]
            );
        }

        return [$mime, ...self::TYPES[$mime]];
    }

    /**
     * Schreibt erst in einen temporären Ordner; bei Fehler wird er gelöscht.
     *
     * @param array{width: int, height: int, ext: string, variants: list<array{w: int, files: list<string>}>}|null $result Ausgabe: Bildmaße und Varianten
     */
    private function writeToTempDir(string $bytes, string $mime, string $kind, string $ext, ?array &$result): string
    {
        $root = $this->mediaRoot();
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new DomainError('store_failed', 'Der Medien-Ordner lässt sich nicht anlegen.');
        }

        $tempDir = $root . '/.tmp-' . bin2hex(random_bytes(6));
        if (!mkdir($tempDir, 0775)) {
            throw new DomainError('store_failed', 'Der temporäre Medien-Ordner lässt sich nicht anlegen.');
        }

        try {
            $result = null;
            if ($kind === 'image') {
                $image = $this->images->process($bytes, $mime, $tempDir);
                $result = ['width' => $image->width, 'height' => $image->height, 'ext' => $image->ext, 'variants' => $image->variants];
            } elseif (file_put_contents($tempDir . '/original.' . $ext, $bytes) === false) {
                throw new DomainError('store_failed', 'Die Datei lässt sich nicht schreiben.');
            }
        } catch (\Throwable $error) {
            $this->removeDir($tempDir);

            throw $error;
        }

        return $tempDir;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, string|null>
     */
    private function validateMeta(array $meta): array
    {
        $fields = [];
        foreach ($meta as $name => $value) {
            $name = (string) $name;
            if (!isset(self::META_LENGTH[$name])) {
                throw new DomainError('invalid_value', sprintf('Das Feld „%s“ gibt es bei Medien nicht. Gültig: %s.', $name, implode(', ', array_keys(self::META_LENGTH))), ['field' => $name]);
            }
            if ($value !== null && !is_string($value)) {
                throw new DomainError('invalid_value', sprintf('Das Feld „%s“ muss ein Text sein.', $name), ['field' => $name]);
            }

            $text = $value === null ? '' : trim($value);
            if (mb_strlen($text) > self::META_LENGTH[$name]) {
                throw new DomainError('invalid_value', sprintf('Das Feld „%s“ darf höchstens %d Zeichen lang sein.', $name, self::META_LENGTH[$name]), ['field' => $name]);
            }
            if ($text !== '' && in_array($name, self::URL_FIELDS, true) && preg_match('#^https?://\S+$#', $text) !== 1) {
                throw new DomainError('invalid_value', sprintf('Das Feld „%s“ muss leer sein oder mit http:// oder https:// beginnen.', $name), ['field' => $name]);
            }

            $fields[$name] = $text === '' ? null : $text;
        }

        return $fields;
    }

    /** @param array<string, string|null> $fields */
    private function writeMeta(string $uid, array $fields, Actor $actor): void
    {
        $this->media->updateMeta($uid, $fields);
        $this->audit->record($actor, 'media.update', 'media:' . $uid, ['fields' => array_keys($fields)]);
    }

    private function mediaRoot(): string
    {
        return (string) $this->config['storage_dir'] . '/media';
    }

    private function mediaConfig(string $key): mixed
    {
        return ((array) $this->config['media'])[$key];
    }

    private function removeDir(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($directory . '/' . $entry);
            }
        }
        rmdir($directory);
    }
}
