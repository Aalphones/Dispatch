<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Http\Request;

final class DeployToken
{
    public static function matches(Request $request, string $expected): bool
    {
        if ($expected === '') {
            return false;
        }

        // Zweiter Header, falls Strato den Authorization-Header verschluckt.
        $candidate = $request->bearerToken() ?? $request->header('X-Dispatch-Token');
        if ($candidate === null) {
            return false;
        }

        return hash_equals($expected, $candidate);
    }
}
