<?php

declare(strict_types=1);

namespace Dispatch\Site;

/** Die Theme-Dateien als hash-benannte Assets (`assets/style.<sha1-8>.css`), damit Browser sie unbegrenzt cachen können. */
final class Assets
{
    /** Schlüssel der Adressen → [Dateiname ohne Endung, Endung]. */
    private const THEME_FILES = [
        'style' => ['style', 'css'],
        'site' => ['site', 'js'],
        'suche' => ['suche', 'js'],
        'artikel' => ['artikel', 'js'],
        'zaehler' => ['zaehler', 'js'],
        'vorschau' => ['vorschau', 'js'],
        'bausteine' => ['bausteine', 'js'],
        'bausteinestil' => ['bausteine', 'css'],
        'diagramme' => ['diagramme', 'js'],
        'diagrammestil' => ['diagramme', 'css'],
    ];

    private const EDITORIAL_FOLDER = 'redaktion';

    private const HASH_LENGTH = 8;

    /**
     * @param array<string, mixed> $config
     * @return array{files: array<string, string>, urls: array<string, string>} Pfad → Inhalt, Name → URL
     */
    public static function collect(array $config): array
    {
        $files = [];
        $urls = [];
        foreach (self::THEME_FILES as $key => [$name, $extension]) {
            $source = (string) $config['app_dir'] . '/theme/' . $name . '.' . $extension;
            $content = is_file($source) ? (string) file_get_contents($source) : '';

            $path = self::hashedPath($name, $extension, $content);
            $files[$path] = $content;
            $urls[$key] = '/' . $path;
        }

        $editorial = self::collectEditorialFolder((string) $config['app_dir'] . '/theme/' . self::EDITORIAL_FOLDER);
        $files += $editorial['files'];
        if ($editorial['entry'] !== '') {
            $urls['redaktion'] = $editorial['entry'];
        }

        return ['files' => $files, 'urls' => $urls];
    }

    /**
     * Der Ordner des Redaktionsmodus wird unverändert und als Ganzes unter einem Hash-Namen abgelegt: seine
     * Module importieren einander relativ, das ginge mit Einzel-Hashes nicht. Ein geänderter Inhalt ergibt einen
     * neuen Ordnernamen und umgeht damit den Cache.
     *
     * @return array{files: array<string, string>, entry: string} Pfad → Inhalt, Adresse von `start.js`
     */
    private static function collectEditorialFolder(string $folder): array
    {
        $names = [];
        foreach (is_dir($folder) ? (scandir($folder) ?: []) : [] as $name) {
            if (is_file($folder . '/' . $name)) {
                $names[] = $name;
            }
        }
        sort($names, SORT_STRING);
        if ($names === []) {
            return ['files' => [], 'entry' => ''];
        }

        $contents = [];
        foreach ($names as $name) {
            $contents[$name] = (string) file_get_contents($folder . '/' . $name);
        }
        $target = 'assets/' . self::EDITORIAL_FOLDER . '-' . substr(sha1(implode('', $contents)), 0, self::HASH_LENGTH);

        $files = [];
        foreach ($contents as $name => $content) {
            $files[$target . '/' . $name] = $content;
        }

        return ['files' => $files, 'entry' => '/' . $target . '/start.js'];
    }

    /** Auch für Dateien, die der Builder selbst erzeugt (Suchindex). */
    public static function hashedPath(string $name, string $extension, string $content): string
    {
        return 'assets/' . $name . '.' . substr(sha1($content), 0, self::HASH_LENGTH) . '.' . $extension;
    }
}
