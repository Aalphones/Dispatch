<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Util\Env;

/** Signierte Vorschau-Links (ADR-021): lesender Zugriff auf einen Arbeitsstand ohne Anmeldung, sieben Tage gültig. */
final class PreviewLink
{
    private const LIFETIME_SECONDS = 7 * 86400;
    private const MEDIA_LIFETIME_SECONDS = 3600;

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

    /**
     * Schreibt `/media/<uid>/<datei>` in einem Vorschau-HTML auf den geschützten Bild-Endpunkt um (eine Stunde gültig):
     * unveröffentlichte Bilder liegen nicht unter `/media/`. Nur die Bilder des Arbeitsstands (`$ownUids`) werden umgeschrieben;
     * Bilder fremder, schon veröffentlichter Artikel (Karten unter „Mehr dazu“) bleiben öffentliche `/media/`-Adressen.
     *
     * @param list<string> $ownUids
     */
    public static function protectMedia(string $html, int $id, array $ownUids): string
    {
        $expires = time() + self::MEDIA_LIFETIME_SECONDS;
        $query = 'id=' . $id . '&amp;exp=' . $expires . '&amp;sig=' . self::sign($id, $expires) . '&amp;f=';

        return (string) preg_replace_callback(
            '#/media/([a-z0-9]+)/([A-Za-z0-9._-]+)#',
            static fn (array $m): string => in_array($m[1], $ownUids, true)
                ? '/dispatch/api/preview-media?' . $query . $m[1] . '%2F' . $m[2]
                : $m[0],
            $html
        );
    }

    private static function sign(int $id, int $expires): string
    {
        return hash_hmac('sha256', 'preview:' . $id . ':' . $expires, Env::get('DISPATCH_JWT_SECRET'));
    }
}
