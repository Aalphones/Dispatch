<?php

declare(strict_types=1);

namespace Dispatch\Util;

use Dispatch\Domain\DomainError;
use GdImage;

/**
 * Kodiert Bilder immer neu und speichert nie das Original-Byte: EXIF, GPS und eingebettete Daten fallen weg.
 */
final class ImageProcessor
{
    private const ORIGINAL_EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'png'];

    private const ALPHA_MIMES = ['image/png', 'image/webp', 'image/gif'];

    /** @param array<string, mixed> $mediaConfig */
    public function __construct(private readonly array $mediaConfig)
    {
    }

    public function process(string $bytes, string $mime, string $targetDir): ImageResult
    {
        $size = getimagesizefromstring($bytes);
        if ($size === false) {
            throw new DomainError('type_rejected', 'Die Bilddatei ist beschädigt oder kein unterstütztes Bild.');
        }

        [$width, $height] = $size;
        $maxMegapixels = (int) $this->mediaConfig['max_megapixels'];
        if ($width * $height > $maxMegapixels * 1_000_000) {
            throw new DomainError(
                'too_many_pixels',
                sprintf('Das Bild hat %.1f Megapixel; erlaubt sind höchstens %d. Bitte verkleinern.', $width * $height / 1_000_000, $maxMegapixels),
                ['width' => $width, 'height' => $height]
            );
        }

        $image = imagecreatefromstring($bytes);
        if ($image === false) {
            throw new DomainError('type_rejected', 'Die Bilddatei lässt sich nicht lesen.');
        }
        imagepalettetotruecolor($image);

        if ($mime === 'image/jpeg') {
            $image = $this->applyOrientation($image, $this->readOrientation($bytes));
            $width = imagesx($image);
            $height = imagesy($image);
        }

        $hasAlpha = in_array($mime, self::ALPHA_MIMES, true);
        $fallbackExt = $hasAlpha ? 'png' : 'jpg';
        $originalExt = self::ORIGINAL_EXT[$mime];

        $this->save($image, $targetDir . '/original.' . $originalExt, $originalExt, $hasAlpha, false);

        $variants = [];
        foreach ($this->variantWidths($width) as $variantWidth) {
            $variants[] = $this->writeVariant($image, $variantWidth, $fallbackExt, $hasAlpha, $targetDir);
        }

        return new ImageResult($width, $height, $originalExt, $variants, memory_get_peak_usage(true));
    }

    /** @return list<int> */
    private function variantWidths(int $width): array
    {
        $widths = array_values(array_filter(
            array_map('intval', (array) $this->mediaConfig['variant_widths']),
            static fn (int $candidate): bool => $candidate <= $width
        ));

        return $widths === [] ? [$width] : $widths;
    }

    /** @return array{w: int, files: list<string>} */
    private function writeVariant(GdImage $image, int $variantWidth, string $fallbackExt, bool $hasAlpha, string $targetDir): array
    {
        $scaled = $variantWidth === imagesx($image) ? $image : imagescale($image, $variantWidth, -1, IMG_BICUBIC);
        if ($scaled === false) {
            throw new DomainError('type_rejected', 'Das Bild lässt sich nicht verkleinern.');
        }

        $webpName = $variantWidth . '.webp';
        $fallbackName = $variantWidth . '.' . $fallbackExt;
        $this->save($scaled, $targetDir . '/' . $webpName, 'webp', $hasAlpha, true);
        $this->save($scaled, $targetDir . '/' . $fallbackName, $fallbackExt, $hasAlpha, true);

        return ['w' => $variantWidth, 'files' => [$webpName, $fallbackName]];
    }

    private function save(GdImage $image, string $path, string $ext, bool $hasAlpha, bool $isVariant): void
    {
        if ($hasAlpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $isSaved = match ($ext) {
            'jpg' => $this->saveJpeg($image, $path, $isVariant),
            'png' => imagepng($image, $path, 6),
            'webp' => imagewebp($image, $path, $isVariant ? (int) $this->mediaConfig['webp_quality'] : 90),
        };
        if (!$isSaved) {
            throw new DomainError('store_failed', 'Die Bilddatei lässt sich nicht schreiben.', ['file' => basename($path)]);
        }
    }

    private function saveJpeg(GdImage $image, string $path, bool $isVariant): bool
    {
        if ($isVariant) {
            imageinterlace($image, true);
        }

        return imagejpeg($image, $path, $isVariant ? (int) $this->mediaConfig['jpeg_quality'] : 92);
    }

    private function readOrientation(string $bytes): int
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'dsp');
        if ($tempFile === false) {
            return 1;
        }

        try {
            file_put_contents($tempFile, $bytes);
            $exif = exif_read_data($tempFile, 'IFD0');

            return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        } catch (\Throwable) {
            return 1;
        } finally {
            unlink($tempFile);
        }
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        // imagerotate dreht gegen den Uhrzeigersinn.
        $steps = match ($orientation) {
            2 => ['flip' => IMG_FLIP_HORIZONTAL, 'angle' => 0],
            3 => ['flip' => null, 'angle' => 180],
            4 => ['flip' => IMG_FLIP_VERTICAL, 'angle' => 0],
            5 => ['flip' => IMG_FLIP_HORIZONTAL, 'angle' => 90],
            6 => ['flip' => null, 'angle' => 270],
            7 => ['flip' => IMG_FLIP_HORIZONTAL, 'angle' => 270],
            8 => ['flip' => null, 'angle' => 90],
            default => null,
        };
        if ($steps === null) {
            return $image;
        }

        if ($steps['flip'] !== null) {
            imageflip($image, $steps['flip']);
        }
        if ($steps['angle'] === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $steps['angle'], 0);

        return $rotated === false ? $image : $rotated;
    }
}
