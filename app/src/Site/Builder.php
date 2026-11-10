<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Domain\DomainError;
use Dispatch\Util\Log;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Erzeugt die statische Website in `web_root` (ADR-014). Die Wurzel teilen sich CardMaker und
 * CamScanner: geschrieben und gelöscht wird nur, was `assertWritable` durchlässt.
 */
final class Builder
{
    private const LOCK_NAME = 'dispatch_build';
    private const LOCK_TIMEOUT = 30;

    private SiteData $site;

    /** @var array<string, string> Name → URL der Theme-Dateien, vom laufenden Bau gesetzt */
    private array $assetUrls = [];

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly PDO $pdo,
        private readonly int $lockTimeout = self::LOCK_TIMEOUT,
    ) {
    }

    /** @return array{written: int, unchanged: int, deleted: int, ms: int} */
    public function build(): array
    {
        $startedAt = hrtime(true);
        $this->acquireLock();

        try {
            $this->site = SiteData::load($this->pdo, $this->config);
            $assets = Assets::collect($this->config);
            $this->assetUrls = $assets['urls'];

            $files = $this->collect($assets['files']);
            $copies = $this->collectMediaCopies();
            $newPaths = array_map('strval', array_keys($files + $copies));

            $manifest = new Manifest((string) $this->config['storage_dir'] . '/manifest.json');
            $oldPaths = $manifest->read();
            // Alt ∪ neu zuerst: bricht der Lauf ab, kennt das Manifest jede Datei, die er angelegt haben kann.
            $manifest->write(array_values(array_unique(array_merge($oldPaths, $newPaths))));

            $written = 0;
            $unchanged = 0;
            foreach ($files as $path => $content) {
                if ($this->writeFile((string) $path, $content)) {
                    $written++;
                } else {
                    $unchanged++;
                }
            }
            foreach ($copies as $target => $source) {
                if ($this->copyMedia((string) $target, $source)) {
                    $written++;
                } else {
                    $unchanged++;
                }
            }

            $deleted = $this->deleteStale($oldPaths, $newPaths);
            $manifest->write($newPaths);
        } finally {
            $this->releaseLock();
        }

        return [
            'written' => $written,
            'unchanged' => $unchanged,
            'deleted' => $deleted,
            'ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /**
     * Alle Seiten im Speicher: Pfad → Inhalt. Spätere Seitentypen kommen als eigene `collect<Seitentyp>()` dazu.
     *
     * @param array<string, string> $assetFiles
     * @return array<string, string>
     */
    private function collect(array $assetFiles): array
    {
        return $assetFiles + $this->collectHome() + $this->collectNotFound();
    }

    /** @return array<string, string> */
    private function collectHome(): array
    {
        $html = $this->page('home', ['posts' => $this->site->posts], [
            'title' => (string) $this->site->settings['site_title'],
            'description' => (string) $this->site->settings['site_description'],
            'canonical' => '/',
            'robots' => 'index, follow',
            'bodyPage' => 'start',
        ]);

        return ['index.html' => $html];
    }

    /** @return array<string, string> */
    private function collectNotFound(): array
    {
        $html = $this->page('404', [], [
            'title' => 'Nicht gefunden · ' . $this->site->settings['site_title'],
            'robots' => 'noindex',
            'bodyPage' => 'fehler',
        ]);

        return ['404.html' => $html];
    }

    /**
     * Nur Varianten der Medien, die ein Live-Artikel nutzt; das Original eines Bildes bleibt im Speicher.
     *
     * @return array<string, string> Ziel (relativ zur Wurzel) → Quelldatei
     */
    private function collectMediaCopies(): array
    {
        $mediaDir = (string) $this->config['storage_dir'] . '/media';

        $copies = [];
        foreach ($this->site->media as $uid => $medium) {
            $names = $medium['kind'] === 'image' ? $this->variantFiles($medium) : ['original.' . $medium['ext']];
            foreach ($names as $name) {
                $source = $mediaDir . '/' . $uid . '/' . $name;
                // Dateinamen kommen aus der Datenbank: nur reine Namen, nie Pfade.
                if ($name !== basename($name) || !is_file($source)) {
                    Log::error('Mediendatei fehlt oder hat einen ungültigen Namen', ['uid' => $uid, 'file' => $name]);

                    continue;
                }
                $copies['media/' . $uid . '/' . $name] = $source;
            }
        }

        return $copies;
    }

    /**
     * @param array<string, mixed> $medium
     * @return list<string>
     */
    private function variantFiles(array $medium): array
    {
        $names = [];
        foreach ((array) $medium['variants'] as $variant) {
            foreach ((array) $variant['files'] as $name) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Rendert die Seitenvorlage und setzt sie in `layout.php`.
     *
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $meta
     */
    public function page(string $template, array $vars, array $meta): string
    {
        $shared = ['site' => $this->site, 'assets' => $this->assetUrls];
        $appDir = (string) $this->config['app_dir'];

        $content = $this->render($appDir . '/templates/' . $template . '.php', $vars + $shared);

        return $this->render($appDir . '/templates/layout.php', $shared + [
            'content' => $content,
            'meta' => $meta,
            'baseUrl' => rtrim((string) $this->config['base_url'], '/'),
        ]);
    }

    /** @param array<string, mixed> $variables */
    public function render(string $template, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        ob_start();
        try {
            include $template;
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return (string) ob_get_clean();
    }

    /** @return bool true, wenn die Datei geschrieben wurde; false, wenn sie schon denselben Inhalt hatte */
    public function writeFile(string $relative, string $content): bool
    {
        $this->assertWritable($relative);

        $target = $this->absolutePath($relative);
        if (is_file($target) && hash_equals((string) sha1_file($target), sha1($content))) {
            return false;
        }

        $directory = $this->prepareDirectory($target, $relative);
        $temporaryFile = $this->temporaryName($directory, $target);
        if (file_put_contents($temporaryFile, $content) === false) {
            throw new RuntimeException('Datei nicht schreibbar: ' . $relative);
        }
        $this->replace($temporaryFile, $target, $relative);

        return true;
    }

    /**
     * Medien ändern sich nie (ADR-011): gleiche Größe heißt gleiche Datei.
     *
     * @return bool true, wenn kopiert wurde
     */
    private function copyMedia(string $relative, string $source): bool
    {
        $this->assertWritable($relative);

        $target = $this->absolutePath($relative);
        if (is_file($target) && filesize($target) === filesize($source)) {
            return false;
        }

        $directory = $this->prepareDirectory($target, $relative);
        $temporaryFile = $this->temporaryName($directory, $target);
        if (!copy($source, $temporaryFile)) {
            throw new RuntimeException('Medium nicht kopierbar: ' . $relative);
        }
        $this->replace($temporaryFile, $target, $relative);

        return true;
    }

    /**
     * Löscht, was das alte Manifest kannte und der neue Bau nicht mehr erzeugt — und sonst nichts.
     *
     * @param list<string> $oldPaths
     * @param list<string> $newPaths
     * @return int Zahl gelöschter Dateien
     */
    private function deleteStale(array $oldPaths, array $newPaths): int
    {
        $deleted = 0;
        foreach (array_diff($oldPaths, $newPaths) as $relative) {
            try {
                $this->assertWritable($relative);
            } catch (RuntimeException $exception) {
                Log::error('Manifest-Eintrag verweigert, nicht gelöscht', ['path' => $relative, 'reason' => $exception->getMessage()]);

                continue;
            }

            $target = $this->absolutePath($relative);
            if (!is_file($target)) {
                continue;
            }
            if (unlink($target)) {
                $deleted++;
                $this->removeEmptyParents($relative);
            }
        }

        return $deleted;
    }

    /** Entfernt leere Elternordner bis unterhalb des ersten Segments; `artikel/` selbst bleibt. */
    private function removeEmptyParents(string $relative): void
    {
        $directory = dirname(str_replace('\\', '/', $relative));
        while (str_contains($directory, '/')) {
            $absolute = $this->absolutePath($directory);
            $entries = is_dir($absolute) ? scandir($absolute) : false;
            if ($entries === false || array_diff($entries, ['.', '..']) !== []) {
                return;
            }
            if (!rmdir($absolute)) {
                return;
            }
            $directory = dirname($directory);
        }
    }

    /**
     * Zweite Schreibsperre neben dem Manifest (ADR-014): nur Pfade unter den eigenen Namen der obersten Ebene.
     *
     * @throws RuntimeException
     */
    private function assertWritable(string $relative): void
    {
        $isAbsolute = str_starts_with($relative, '/')
            || str_starts_with($relative, '\\')
            || preg_match('/^[A-Za-z]:/', $relative) === 1;
        if ($relative === '' || $isAbsolute) {
            throw new RuntimeException('Absoluter oder leerer Pfad: ' . $relative);
        }

        $segments = preg_split('#[/\\\\]#', $relative);
        if ($segments === false || in_array('..', $segments, true)) {
            throw new RuntimeException('Pfad mit ".." verweigert: ' . $relative);
        }

        $reserved = array_map('strtolower', (array) $this->config['reserved_top_level']);
        if (in_array(strtolower($segments[0]), $reserved, true)) {
            throw new RuntimeException('Reservierter Name: ' . $segments[0]);
        }

        // Case-sensitiv: auf Windows-Servern wäre „Card" derselbe Ordner wie „card".
        if (!in_array($segments[0], (array) $this->config['builder_top_level'], true)) {
            throw new RuntimeException('Name nicht in builder_top_level: ' . $segments[0]);
        }
    }

    private function absolutePath(string $relative): string
    {
        return rtrim((string) $this->config['web_root'], '/\\') . '/' . $relative;
    }

    private function prepareDirectory(string $target, string $relative): string
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Zielordner nicht anlegbar: ' . $relative);
        }

        return $directory;
    }

    /** Temporäre Datei im Zielordner, damit rename auf demselben Dateisystem atomar bleibt. */
    private function temporaryName(string $directory, string $target): string
    {
        return $directory . '/.' . basename($target) . '.' . bin2hex(random_bytes(4)) . '.tmp';
    }

    private function replace(string $temporaryFile, string $target, string $relative): void
    {
        if (!rename($temporaryFile, $target)) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
            throw new RuntimeException('Datei nicht ersetzbar: ' . $relative);
        }
    }

    private function acquireLock(): void
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
        $statement->execute([self::LOCK_NAME, $this->lockTimeout]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new DomainError('build_busy', 'Ein anderer Build läuft gerade. Versuche es in einer Minute erneut.');
        }
    }

    private function releaseLock(): void
    {
        $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([self::LOCK_NAME]);
    }
}
