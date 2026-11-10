<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaService;

/** Bild-Blöcke für Tool-Ergebnisse: höchstens 6 je Antwort, Kante ≤ 1024 px. */
final class ImageBlocks
{
    public const MAX_EDGE = 1024;
    public const MAX_BLOCKS = 6;

    private const VARIANT_MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png'];
    private const ORIGINAL_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param list<array<string, mixed>> $records Medien-Datensätze (mit `uid`, `kind`, …); Nicht-Bilder werden ignoriert
     * @return array{blocks: list<array<string, string>>, skipped: list<string>} `skipped` = UIDs ohne Block (Grenze oder nicht darstellbar)
     */
    public static function forMedia(MediaService $media, array $records, int $max = self::MAX_BLOCKS): array
    {
        $blocks = [];
        $skipped = [];
        foreach ($records as $record) {
            if (($record['kind'] ?? 'image') !== 'image') {
                continue;
            }
            $uid = (string) $record['uid'];
            $block = count($blocks) < $max ? self::block($media, $record) : null;
            if ($block === null) {
                $skipped[] = $uid;
                continue;
            }
            $blocks[] = $block;
        }

        return ['blocks' => $blocks, 'skipped' => $skipped];
    }

    /**
     * @param array<string, mixed> $record
     * @return array{type: string, data: string, mimeType: string}|null
     */
    private static function block(MediaService $media, array $record): ?array
    {
        $uid = (string) $record['uid'];
        if (!isset($record['variants'], $record['ext'])) {
            $record = $media->get($uid);
        }
        $choice = self::choose($record);
        if ($choice === null) {
            return null;
        }
        try {
            $bytes = file_get_contents($media->filePath($uid, $choice['file']));
        } catch (DomainError) {
            return null;
        }
        if ($bytes === false) {
            return null;
        }

        return ['type' => 'image', 'data' => base64_encode($bytes), 'mimeType' => $choice['mime']];
    }

    /**
     * Größte Variante mit Breite ≤ 1024 als jpg/png; sonst das Original, wenn es klein genug und darstellbar ist.
     *
     * @param array<string, mixed> $record
     * @return array{file: string, mime: string}|null
     */
    private static function choose(array $record): ?array
    {
        $best = null;
        $originalWidth = max((int) ($record['width'] ?? 0), 1);
        $originalHeight = (int) ($record['height'] ?? 0);
        foreach ($record['variants'] as $variant) {
            $variantEdge = max((int) $variant['w'], (int) round((int) $variant['w'] * $originalHeight / $originalWidth));
            if ($variantEdge > self::MAX_EDGE || ($best !== null && (int) $variant['w'] <= $best['width'])) {
                continue;
            }
            foreach ($variant['files'] as $file) {
                $extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));
                if (isset(self::VARIANT_MIME[$extension])) {
                    $best = ['file' => (string) $file, 'mime' => self::VARIANT_MIME[$extension], 'width' => (int) $variant['w']];
                    break;
                }
            }
        }
        if ($best !== null) {
            return ['file' => $best['file'], 'mime' => $best['mime']];
        }

        $width = max((int) ($record['width'] ?? 0), (int) ($record['height'] ?? 0));
        if ($width > 0 && $width <= self::MAX_EDGE && in_array($record['mime'], self::ORIGINAL_MIMES, true)) {
            return ['file' => 'original.' . $record['ext'], 'mime' => (string) $record['mime']];
        }

        return null;
    }
}
