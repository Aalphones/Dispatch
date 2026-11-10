<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\TokenService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/** Token-Verwaltung für `bin/dispatch token:* --remote`, geschützt mit dem Deploy-Token (ADR-017). */
final class TokenController
{
    public function handle(Request $request): never
    {
        if (!DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'))) {
            Response::error('unauthorized', 'Deploy-Token fehlt oder ist falsch.', 401);
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            Response::error('invalid_value', 'Der Body muss ein JSON-Objekt sein, z. B. {"action":"list"}.', 400);
        }

        $service = new TokenService(Connection::open());
        $actor = Actor::deploy();

        try {
            match ($body['action'] ?? null) {
                'create' => Response::json($this->create($service, $body, $actor), 201),
                'list' => Response::json(['tokens' => $service->list()]),
                'revoke' => Response::json($this->revoke($service, $body, $actor)),
                default => Response::error('invalid_value', 'Unbekannte action. Gültig: create, list, revoke.', 400),
            };
        } catch (DomainError $error) {
            Response::error($error->errorCode, $error->getMessage(), 400);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function create(TokenService $service, array $body, Actor $actor): array
    {
        $name = $body['name'] ?? null;
        $scopes = $body['scopes'] ?? ['blog:read', 'blog:write'];
        $expiresDays = $body['expires_days'] ?? null;
        if (!is_string($name)) {
            throw new DomainError('invalid_value', 'name fehlt (Text, z. B. claude-code).', ['field' => 'name']);
        }
        if (!is_array($scopes) && !is_string($scopes)) {
            throw new DomainError('invalid_value', 'scopes muss eine Liste sein, z. B. ["blog:read"].', ['field' => 'scopes']);
        }
        if ($expiresDays !== null && !is_int($expiresDays)) {
            throw new DomainError('invalid_value', 'expires_days muss eine ganze Zahl oder null sein.', ['field' => 'expires_days']);
        }

        return $service->create($name, $scopes, $expiresDays, $actor);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function revoke(TokenService $service, array $body, Actor $actor): array
    {
        $id = $body['id'] ?? null;
        if (!is_int($id)) {
            throw new DomainError('invalid_value', 'id fehlt (ganze Zahl aus der Token-Liste).', ['field' => 'id']);
        }
        $service->revoke($id, $actor);

        return ['revoked' => $id];
    }
}
