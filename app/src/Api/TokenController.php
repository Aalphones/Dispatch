<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\TokenService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/**
 * Zugänge für Agenten (ADR-017). Der Redaktionsmodus nutzt `list`, `issue` und `revoke` mit der Anmeldung des Inhabers;
 * `bin/dispatch token:* --remote` nutzt `handle` mit dem Deploy-Token und einer `action` im Body.
 */
final class TokenController
{
    private const DEFAULT_SCOPES = ['blog:read', 'blog:write'];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /** `POST tokens`: Deploy-Token mit `action` → Kommandozeilenweg; sonst legt Anmeldung (oder Deploy-Token) einen Zugang an. */
    public function handle(Request $request): never
    {
        $isDeploy = DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'));
        if ($isDeploy && array_key_exists('action', $this->looseBody($request))) {
            $this->handleDeploy($request);
        }

        $this->issue($request);
    }

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        $tokens = [];
        foreach ((new TokenService(Connection::open()))->list() as $token) {
            if ($token['revoked']) {
                continue;
            }
            unset($token['revoked']);
            $tokens[] = $token;
        }

        Response::json(['tokens' => $tokens]);
    }

    public function revokeById(Request $request, int $id): never
    {
        $actor = ApiAuth::ownerOrDeploy($request);

        try {
            (new TokenService(Connection::open()))->revoke($id, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }

    /** Legt einen Zugang an; der Klartext steht nur in dieser Antwort. Keine Passwortabfrage (Entscheidung des Inhabers, ADR-020). */
    private function issue(Request $request): never
    {
        $actor = ApiAuth::ownerOrDeploy($request);

        try {
            $created = $this->create(new TokenService(Connection::open()), $request->json(), $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json([
            'token' => $created['token'],
            'id' => $created['id'],
            'name' => $created['name'],
            'scopes' => $created['scopes'],
            'expires_at' => $created['expires_at'],
            'command' => $this->command($created['token']),
        ], 201);
    }

    private function command(string $plain): string
    {
        return sprintf(
            'claude mcp add --transport http dispatch %s/dispatch/mcp --header "Authorization: Bearer %s"',
            rtrim((string) $this->config['base_url'], '/'),
            $plain
        );
    }

    /** @return array<string, mixed> */
    private function looseBody(Request $request): array
    {
        $decoded = json_decode($request->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function handleDeploy(Request $request): never
    {
        $body = $this->looseBody($request);

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
        $scopes = $body['scopes'] ?? self::DEFAULT_SCOPES;
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
