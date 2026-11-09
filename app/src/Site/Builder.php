<?php

declare(strict_types=1);

namespace Dispatch\Site;

use RuntimeException;
use Throwable;

final class Builder
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /** @return list<string> relative Pfade der geschriebenen Dateien */
    public function buildPlaceholder(): array
    {
        $appDir = (string) $this->config['app_dir'];

        $css = (string) file_get_contents($appDir . '/theme/placeholder.css');
        $cssPath = 'assets/placeholder.' . substr(sha1($css), 0, 8) . '.css';
        $variables = ['css' => '/' . $cssPath];

        $this->writeFile($cssPath, $css);
        $this->writeFile('index.html', $this->render($appDir . '/templates/placeholder.php', $variables));
        $this->writeFile('404.html', $this->render($appDir . '/templates/404.php', $variables));

        $files = ['index.html', '404.html', $cssPath];
        (new Manifest((string) $this->config['storage_dir'] . '/manifest.json'))->write($files);

        return $files;
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

    public function writeFile(string $relative, string $content): void
    {
        $this->assertWritable($relative);

        $target = rtrim((string) $this->config['web_root'], '/\\') . '/' . $relative;
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Zielordner nicht anlegbar: ' . $relative);
        }

        // Temporäre Datei im Zielordner, damit rename auf demselben Dateisystem atomar bleibt.
        $temporaryFile = $directory . '/.' . basename($target) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporaryFile, $content) === false) {
            throw new RuntimeException('Datei nicht schreibbar: ' . $relative);
        }
        if (!rename($temporaryFile, $target)) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
            throw new RuntimeException('Datei nicht ersetzbar: ' . $relative);
        }
    }

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
    }
}
