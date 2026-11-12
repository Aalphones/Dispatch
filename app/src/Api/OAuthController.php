<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\OAuth\Consent;
use Dispatch\OAuth\TokenIssuer;

/** Redaktions-API für die Zustimmung zu OAuth-Anfragen und die Liste der verbundenen Apps; nur mit der Anmeldung des Inhabers. */
final class OAuthController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function request(Request $request, string $slug): never
    {
        ApiAuth::owner($request);

        try {
            Response::json((new Consent(Connection::open(), $this->config))->describe($slug));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }
    }

    public function consent(Request $request): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $body = $request->json();
            $requestId = $body['request_id'] ?? null;
            $decision = $body['decision'] ?? null;
            $scopes = $body['scopes'] ?? [];
            if (!is_string($requestId)) {
                throw new DomainError('invalid_value', 'request_id fehlt.', ['field' => 'request_id']);
            }
            if ($decision !== 'allow' && $decision !== 'deny') {
                throw new DomainError('invalid_value', 'decision muss "allow" oder "deny" sein.', ['field' => 'decision']);
            }
            if (!is_array($scopes) || !array_is_list($scopes) || array_filter($scopes, 'is_string') !== $scopes) {
                throw new DomainError('invalid_value', 'scopes muss eine Liste von Texten sein.', ['field' => 'scopes']);
            }

            $redirect = (new Consent(Connection::open(), $this->config))->decide($requestId, $decision === 'allow', $scopes, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['redirect' => $redirect]);
    }

    public function grants(Request $request): never
    {
        ApiAuth::owner($request);

        Response::json(['grants' => (new TokenIssuer(Connection::open(), $this->config))->grants()]);
    }

    public function disconnect(Request $request, string $slug): never
    {
        $actor = ApiAuth::owner($request);

        try {
            (new TokenIssuer(Connection::open(), $this->config))->disconnect($slug, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }
}
