<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use RuntimeException;

/** Protokollfehler: wird als JSON-RPC-Fehler mit passendem HTTP-Status beantwortet. */
final class ProtocolError extends RuntimeException
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    public const INSUFFICIENT_SCOPE = -32003;
    public const HEADER_MISMATCH = -32020;
    public const UNSUPPORTED_VERSION = -32022;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $rpcCode,
        string $message,
        public readonly int $httpStatus = 400,
        public readonly array $data = [],
        public readonly array $headers = []
    ) {
        parent::__construct($message);
    }
}
