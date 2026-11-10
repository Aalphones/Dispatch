<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Domain\TokenService;
use Dispatch\Http\Request;

/** Anmeldung am MCP-Endpunkt: nur Tokens mit dem Präfix `dmcp_`; JWTs fallen hier durch. */
final class McpAuth
{
    private const PREFIX = 'dmcp_';

    /** @return array{id: int, name: string, scopes: list<string>}|null */
    public static function authenticate(Request $request, TokenService $tokens): ?array
    {
        $plain = $request->bearerToken();
        if ($plain === null || !str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        return $tokens->authenticate($plain);
    }
}
