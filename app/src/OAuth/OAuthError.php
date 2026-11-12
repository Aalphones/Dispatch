<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use RuntimeException;

/** Fehler im OAuth-Format `{"error","error_description"}` (RFC 6749 §5.2). */
final class OAuthError extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status = 400
    ) {
        parent::__construct($description);
    }
}
