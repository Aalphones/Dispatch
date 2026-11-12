<?php

declare(strict_types=1);

namespace Dispatch\OAuth;

/** Discovery-Dokumente (RFC 9728, RFC 8414) und der `WWW-Authenticate`-Hinweis des MCP-Endpunkts; reine Funktionen ohne Datenbank. */
final class Discovery
{
    private const BLOG_SCOPES = ['blog:read', 'blog:write', 'blog:admin'];
    private const CHALLENGE_SCOPE = 'blog:read blog:write';
    private const RESOURCE_PATH = '/dispatch/mcp';
    private const METADATA_PATH = '/.well-known/oauth-protected-resource/dispatch/mcp';

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function protectedResource(array $config): array
    {
        $base = self::base($config);

        return [
            'resource' => $base . self::RESOURCE_PATH,
            'authorization_servers' => [$base],
            'scopes_supported' => self::BLOG_SCOPES,
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'DISPATCH',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function authorizationServer(array $config): array
    {
        $base = self::base($config);

        return [
            'issuer' => $base,
            'authorization_endpoint' => $base . '/dispatch/oauth/authorize',
            'token_endpoint' => $base . '/dispatch/oauth/token',
            'revocation_endpoint' => $base . '/dispatch/oauth/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'scopes_supported' => [...self::BLOG_SCOPES, 'offline_access'],
            'client_id_metadata_document_supported' => true,
            'authorization_response_iss_parameter_supported' => true,
        ];
    }

    /**
     * Wert des `WWW-Authenticate`-Kopfs bei 401; `error` nur, wenn ein Token vorlag.
     *
     * @param array<string, mixed> $config
     */
    public static function challengeHeader(array $config, ?string $error): string
    {
        $parameters = [];
        if ($error !== null) {
            $parameters[] = sprintf('error="%s"', $error);
        }
        $parameters[] = sprintf('resource_metadata="%s"', self::base($config) . self::METADATA_PATH);
        $parameters[] = sprintf('scope="%s"', self::CHALLENGE_SCOPE);

        return 'Bearer ' . implode(', ', $parameters);
    }

    /** @param array<string, mixed> $config */
    private static function base(array $config): string
    {
        return rtrim((string) ($config['base_url'] ?? ''), '/');
    }
}
