<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

use Dispatch\Db\Connection;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** `POST /dispatch/oauth/token` und `POST /dispatch/oauth/revoke`; Fehler im OAuth-Format (RFC 6749 §5.2). */
final class TokenController
{
    private const FORM_TYPE = 'application/x-www-form-urlencoded';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function token(Request $request): never
    {
        try {
            $form = $this->form($request);
            $issuer = new TokenIssuer(Connection::open(), $this->config);
            $response = match ($form['grant_type'] ?? null) {
                'authorization_code' => $issuer->exchangeCode($form),
                'refresh_token' => $issuer->refresh($form),
                default => throw new OAuthError('unsupported_grant_type', 'Unterstützt werden authorization_code und refresh_token.'),
            };
        } catch (OAuthError $error) {
            $this->fail($error);
        }

        Response::json($response, 200, ['Pragma' => 'no-cache']);
    }

    public function revoke(Request $request): never
    {
        try {
            $form = $this->form($request);
            $token = $form['token'] ?? null;
            if (!is_string($token) || $token === '') {
                throw new OAuthError('invalid_request', 'Der Parameter token fehlt.');
            }

            $clientId = $form['client_id'] ?? null;
            $secret = $form['client_secret'] ?? null;
            (new TokenIssuer(Connection::open(), $this->config))->revoke(
                $token,
                is_string($clientId) && $clientId !== '' ? $clientId : null,
                is_string($secret) ? $secret : null
            );
        } catch (OAuthError $error) {
            $this->fail($error);
        }

        Response::empty(200);
    }

    /**
     * @return array<string, mixed>
     * @throws OAuthError `invalid_request` bei falschem Content-Type
     */
    private function form(Request $request): array
    {
        if ($request->mediaType() !== self::FORM_TYPE) {
            throw new OAuthError('invalid_request', 'Der Content-Type muss application/x-www-form-urlencoded sein.');
        }

        return $request->form();
    }

    private function fail(OAuthError $error): never
    {
        Response::json(['error' => $error->error, 'error_description' => $error->description], $error->status, ['Pragma' => 'no-cache']);
    }
}
