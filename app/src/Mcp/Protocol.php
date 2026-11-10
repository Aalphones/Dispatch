<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Dispatch\Http\Request;

/** Erkennt die Protokoll-Generation einer Anfrage und gleicht Header mit dem Body ab (ADR-016). */
final class Protocol
{
    public const MODERN = '2026-07-28';
    public const LEGACY = ['2025-11-25', '2025-06-18', '2025-03-26'];
    public const META_VERSION = 'io.modelcontextprotocol/protocolVersion';
    public const META_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';
    public const META_SERVER_INFO = 'io.modelcontextprotocol/serverInfo';

    private const HEADER_VERSION = 'MCP-Protocol-Version';
    private const HEADER_METHOD = 'Mcp-Method';
    private const HEADER_NAME = 'Mcp-Name';
    private const BASE64_PREFIX = '=?base64?';
    private const BASE64_SUFFIX = '?=';
    /** Methoden, deren Mcp-Name-Header ein Feld aus `params` spiegelt. */
    private const NAME_FIELDS = ['tools/call' => 'name', 'prompts/get' => 'name', 'resources/read' => 'uri'];

    /** @return list<string> */
    public static function supported(): array
    {
        return array_merge([self::MODERN], self::LEGACY);
    }

    /**
     * @param array<string, mixed> $message dekodierte JSON-RPC-Anfrage (mit `method`)
     * @return array{version: string, modern: bool}
     */
    public static function negotiate(Request $request, array $message): array
    {
        $method = (string) $message['method'];
        if ($method === 'initialize') {
            return self::negotiateInitialize($message);
        }

        $headerVersion = $request->header(self::HEADER_VERSION);
        $metaVersion = self::metaVersion($message);
        if ($headerVersion !== null && $metaVersion !== null && $headerVersion !== $metaVersion) {
            throw new ProtocolError(
                ProtocolError::HEADER_MISMATCH,
                sprintf('Header MCP-Protocol-Version (%s) und _meta["%s"] (%s) weichen ab.', $headerVersion, self::META_VERSION, $metaVersion)
            );
        }

        $version = $headerVersion ?? $metaVersion;
        if ($version === null) {
            return ['version' => '2025-03-26', 'modern' => false];
        }
        if (in_array($version, self::LEGACY, true)) {
            return ['version' => $version, 'modern' => false];
        }
        if ($version !== self::MODERN) {
            throw new ProtocolError(
                ProtocolError::UNSUPPORTED_VERSION,
                'Unbekannte Protokollversion.',
                400,
                ['supported' => self::supported(), 'requested' => $version]
            );
        }

        self::assertModernRequest($request, $message, $headerVersion);

        return ['version' => self::MODERN, 'modern' => true];
    }

    /** @param array<string, mixed> $message */
    private static function negotiateInitialize(array $message): array
    {
        $params = $message['params'] ?? null;
        $requested = is_array($params) ? ($params['protocolVersion'] ?? null) : null;
        $version = is_string($requested) && in_array($requested, self::LEGACY, true) ? $requested : self::LEGACY[0];

        return ['version' => $version, 'modern' => false];
    }

    /** @param array<string, mixed> $message */
    private static function metaVersion(array $message): ?string
    {
        $params = $message['params'] ?? null;
        $meta = is_array($params) ? ($params['_meta'] ?? null) : null;
        $version = is_array($meta) ? ($meta[self::META_VERSION] ?? null) : null;

        return is_string($version) ? $version : null;
    }

    /** @param array<string, mixed> $message */
    private static function assertModernRequest(Request $request, array $message, ?string $headerVersion): void
    {
        if ($headerVersion === null) {
            throw new ProtocolError(ProtocolError::HEADER_MISMATCH, 'Header MCP-Protocol-Version fehlt (erwartet: ' . self::MODERN . ').');
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];
        foreach ([self::META_VERSION, self::META_CAPABILITIES] as $key) {
            if (!array_key_exists($key, $meta)) {
                throw new ProtocolError(ProtocolError::INVALID_PARAMS, sprintf('params._meta["%s"] fehlt.', $key));
            }
        }

        $method = (string) $message['method'];
        if ($request->header(self::HEADER_METHOD) !== $method) {
            throw new ProtocolError(
                ProtocolError::HEADER_MISMATCH,
                sprintf('Header Mcp-Method fehlt oder weicht vom Body ab (erwartet: %s).', $method)
            );
        }

        $nameField = self::NAME_FIELDS[$method] ?? null;
        if ($nameField === null) {
            return;
        }
        $expected = $params[$nameField] ?? null;
        $actual = self::decodeHeaderValue($request->header(self::HEADER_NAME));
        if (!is_string($expected) || $actual !== $expected) {
            throw new ProtocolError(
                ProtocolError::HEADER_MISMATCH,
                sprintf('Header Mcp-Name fehlt oder weicht von params.%s ab.', $nameField)
            );
        }
    }

    /** Header-Werte außerhalb von ASCII kommen als `=?base64?…?=` (UTF-8). */
    private static function decodeHeaderValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $isEncoded = str_starts_with($value, self::BASE64_PREFIX) && str_ends_with($value, self::BASE64_SUFFIX);
        if (!$isEncoded) {
            return $value;
        }

        $encoded = substr($value, strlen(self::BASE64_PREFIX), -strlen(self::BASE64_SUFFIX));
        $decoded = base64_decode($encoded, true);

        return $decoded === false ? null : $decoded;
    }
}
