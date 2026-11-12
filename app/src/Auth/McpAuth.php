<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Domain\TokenService;
use Dispatch\Http\Request;
use Dispatch\OAuth\TokenIssuer;

/** Anmeldung am MCP-Endpunkt: feste Tokens `dmcp_` und OAuth-Access-Tokens `doa_`; JWTs fallen hier durch. */
final class McpAuth
{
    private const PREFIX = 'dmcp_';

    /** @return array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'}|null */
    public static function authenticate(Request $request, TokenService $tokens, TokenIssuer $oauth): ?array
    {
        $plain = $request->bearerToken();
        if ($plain === null) {
            return null;
        }

        if (str_starts_with($plain, self::PREFIX)) {
            $auth = $tokens->authenticate($plain);

            return $auth === null ? null : $auth + ['kind' => 'mcp'];
        }
        if (str_starts_with($plain, TokenIssuer::ACCESS_PREFIX)) {
            $auth = $oauth->authenticate($plain);

            return $auth === null ? null : $auth + ['kind' => 'oauth'];
        }

        return null;
    }
}
