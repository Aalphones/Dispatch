<?php

declare(strict_types=1);

namespace Dispatch\Site;

/** Die Theme-Dateien als hash-benannte Assets (`assets/style.<sha1-8>.css`), damit Browser sie unbegrenzt cachen können. */
final class Assets
{
    private const THEME_FILES = [
        'style' => 'css',
        'site' => 'js',
        'suche' => 'js',
        'artikel' => 'js',
        'zaehler' => 'js',
        'vorschau' => 'js',
    ];

    private const HASH_LENGTH = 8;

    /**
     * @param array<string, mixed> $config
     * @return array{files: array<string, string>, urls: array<string, string>} Pfad → Inhalt, Name → URL
     */
    public static function collect(array $config): array
    {
        $files = [];
        $urls = [];
        foreach (self::THEME_FILES as $name => $extension) {
            $source = (string) $config['app_dir'] . '/theme/' . $name . '.' . $extension;
            $content = is_file($source) ? (string) file_get_contents($source) : '';

            $path = self::hashedPath($name, $extension, $content);
            $files[$path] = $content;
            $urls[$name] = '/' . $path;
        }

        return ['files' => $files, 'urls' => $urls];
    }

    /** Auch für Dateien, die der Builder selbst erzeugt (Suchindex). */
    public static function hashedPath(string $name, string $extension, string $content): string
    {
        return 'assets/' . $name . '.' . substr(sha1($content), 0, self::HASH_LENGTH) . '.' . $extension;
    }
}
