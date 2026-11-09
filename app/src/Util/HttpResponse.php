<?php

declare(strict_types=1);

namespace Dispatch\Util;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
        public string $finalUrl,
    ) {
    }
}
