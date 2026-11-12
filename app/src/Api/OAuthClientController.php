<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\OAuth\ClientRegistry;

/** Rückfall-Clients für Claude-Apps ohne CIMD (Meilenstein 5). Nur mit Deploy-Token; `bin/dispatch oauth-client:* --remote` ruft sie auf. */
final class OAuthClientController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function create(Request $request): never
    {
        $actor = ApiAuth::deployOnly($request);

        $body = json_decode($request->body(), true);
        $name = is_array($body) ? ($body['name'] ?? null) : null;

        try {
            if (!is_string($name)) {
                throw new DomainError('invalid_value', 'name fehlt (Text, z. B. claude-app).', ['field' => 'name']);
            }
            $created = $this->registry()->createManual($name, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($created, 201);
    }

    public function list(Request $request): never
    {
        ApiAuth::deployOnly($request);

        Response::json(['clients' => $this->registry()->listManual()]);
    }

    public function revoke(Request $request, int $id): never
    {
        $actor = ApiAuth::deployOnly($request);

        try {
            $this->registry()->revokeManual($id, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry(Connection::open(), $this->config);
    }
}
