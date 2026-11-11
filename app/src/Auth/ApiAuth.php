<?php

declare(strict_types=1);

namespace Dispatch\Auth;

use Dispatch\Domain\Actor;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/** Anmeldung an `/dispatch/api/*`: nur Access-JWTs des Inhabers (und wo genannt das Deploy-Token); `dmcp_`-Tokens ergeben 401. */
final class ApiAuth
{
    private const MCP_PREFIX = 'dmcp_';

    public static function owner(Request $request): Actor
    {
        if (!self::hasValidJwt($request)) {
            self::reject();
        }

        return Actor::owner();
    }

    public static function ownerOrDeploy(Request $request): Actor
    {
        if (DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'))) {
            return Actor::deploy();
        }

        return self::owner($request);
    }

    private static function hasValidJwt(Request $request): bool
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || str_starts_with($bearer, self::MCP_PREFIX)) {
            return false;
        }

        return Jwt::decode($bearer) !== null;
    }

    private static function reject(): never
    {
        Response::error(
            'unauthorized',
            'Anmeldung fehlt oder ist abgelaufen.',
            401,
            ['WWW-Authenticate' => 'Bearer realm="dispatch-api"']
        );
    }
}
