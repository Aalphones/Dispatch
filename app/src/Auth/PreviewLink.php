<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Util\Env;

/** Signierte Vorschau-Links (ADR-021): lesender Zugriff auf einen Arbeitsstand ohne Anmeldung, sieben Tage gültig. */
final class PreviewLink
{
    private const LIFETIME_SECONDS = 7 * 86400;

    public static function url(int $id): string
    {
        $expires = time() + self::LIFETIME_SECONDS;

        return rtrim(Env::get('DISPATCH_BASE_URL'), '/') . '/vorschau/?' . http_build_query([
            'id' => $id,
            'exp' => $expires,
            'sig' => self::sign($id, $expires),
        ]);
    }

    public static function verify(int $id, int $expires, string $signature): bool
    {
        if (Env::get('DISPATCH_JWT_SECRET') === '' || $expires <= time()) {
            return false;
        }

        return hash_equals(self::sign($id, $expires), $signature);
    }

    private static function sign(int $id, int $expires): string
    {
        return hash_hmac('sha256', 'preview:' . $id . ':' . $expires, Env::get('DISPATCH_JWT_SECRET'));
    }
}
