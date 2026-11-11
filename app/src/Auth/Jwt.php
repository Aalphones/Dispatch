<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Util\Env;

/** Access-JWT des Inhabers (ADR-020): HS256 fest, ein `alg` aus dem Token wird nie übernommen. */
final class Jwt
{
    public const LIFETIME_SECONDS = 900;
    private const SUBJECT = 'owner';
    private const API_PATH = '/dispatch/api';

    /** @param array<string, mixed> $claims */
    public static function encode(array $claims): string
    {
        $header = self::base64UrlEncode('{"alg":"HS256","typ":"JWT"}');
        $payload = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = self::base64UrlEncode(self::sign($header . '.' . $payload));

        return $header . '.' . $payload . '.' . $signature;
    }

    /** @return array<string, mixed>|null Claims, wenn das Token gültig ist, sonst null */
    public static function decode(string $jwt): ?array
    {
        if (Env::get('DISPATCH_JWT_SECRET') === '') {
            return null;
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerPart, $payloadPart, $signaturePart] = $parts;

        $header = self::decodeJson($headerPart);
        if ($header === null || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $signature = self::base64UrlDecode($signaturePart);
        if ($signature === null || !hash_equals(self::sign($headerPart . '.' . $payloadPart), $signature)) {
            return null;
        }

        $claims = self::decodeJson($payloadPart);
        if ($claims === null || !self::claimsAreValid($claims)) {
            return null;
        }

        return $claims;
    }

    /** @return array<string, mixed> */
    public static function newAccessClaims(): array
    {
        $now = time();

        return [
            'iss' => self::baseUrl(),
            'aud' => self::baseUrl() . self::API_PATH,
            'sub' => self::SUBJECT,
            'iat' => $now,
            'exp' => $now + self::LIFETIME_SECONDS,
            'jti' => bin2hex(random_bytes(16)),
        ];
    }

    /** @param array<string, mixed> $claims */
    private static function claimsAreValid(array $claims): bool
    {
        $expires = $claims['exp'] ?? null;
        if (!is_int($expires) || $expires <= time()) {
            return false;
        }

        return ($claims['iss'] ?? null) === self::baseUrl()
            && ($claims['aud'] ?? null) === self::baseUrl() . self::API_PATH
            && ($claims['sub'] ?? null) === self::SUBJECT;
    }

    private static function baseUrl(): string
    {
        return Env::get('DISPATCH_BASE_URL');
    }

    private static function sign(string $signingInput): string
    {
        return hash_hmac('sha256', $signingInput, Env::get('DISPATCH_JWT_SECRET'), true);
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(string $part): ?array
    {
        $json = self::base64UrlDecode($part);
        if ($json === null) {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $encoded): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $encoded) !== 1) {
            return null;
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
