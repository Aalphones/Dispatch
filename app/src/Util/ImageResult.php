<?php

declare(strict_types=1);

namespace Dispatch\Util;

final readonly class ImageResult
{
    /** @param list<array{w: int, files: list<string>}> $variants */
    public function __construct(
        public int $width,
        public int $height,
        public string $ext,
        public array $variants,
        public int $peakBytes,
    ) {
    }
}
