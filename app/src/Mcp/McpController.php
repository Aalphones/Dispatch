<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Dispatch\Auth\McpAuth;
use Dispatch\Auth\Scopes;
use Dispatch\OAuth\TokenIssuer;
use Dispatch\Db\Connection;
use Dispatch\Domain\BriefingService;
use Dispatch\Domain\CategoryService;
use Dispatch\Domain\ChangeService;
use Dispatch\Domain\DocumentService;
use Dispatch\Domain\MediaService;
use Dispatch\Domain\PostService;
use Dispatch\Domain\QueueService;
use Dispatch\Domain\SettingService;
use Dispatch\Domain\SiteInfoService;
use Dispatch\Domain\StatsService;
use Dispatch\Domain\TokenService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Mcp\Tools\BriefingTools;
use Dispatch\Mcp\Tools\CategoryTools;
use Dispatch\Mcp\Tools\ChangeTools;
use Dispatch\Mcp\Tools\DocumentTools;
use Dispatch\Mcp\Tools\MediaTools;
use Dispatch\Mcp\Tools\PostTools;
use Dispatch\Mcp\Tools\QueueTools;
use Dispatch\Mcp\Tools\SettingTools;
use Dispatch\Mcp\Tools\SiteTools;
use Dispatch\Mcp\Tools\StatsTools;
use Dispatch\OAuth\Discovery;
use Dispatch\Util\Log;
use PDO;
use stdClass;
use Throwable;

/** Endpunkt `/dispatch/mcp`: beide Protokoll-Generationen, Anmeldung, Dispatch der Methoden (ADR-016). */
final class McpController
{
    private const SERVER_NAME = 'dispatch';
    private const SERVER_TITLE = 'DISPATCH';
    private const SERVER_VERSION = '0.3.0';
    private const CACHE_TTL_MS = 300000;
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Services einmal pro Anfrage; `pdo` und `config` brauchen die Website-Tools für den Builder.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function services(PDO $pdo, array $config): array
    {
        return [
            'documents' => new DocumentService($pdo, $config),
            'tokens' => new TokenService($pdo),
            'categories' => new CategoryService($pdo, $config),
            'settings' => new SettingService($pdo, $config),
            'changes' => new ChangeService($pdo, $config),
            'queue' => new QueueService($pdo, $config),
            'posts' => new PostService($pdo, $config),
            'media' => new MediaService($pdo, $config),
            'briefing' => new BriefingService($pdo, $config),
            'site_info' => new SiteInfoService($pdo, $config),
            'stats' => new StatsService($pdo),
            'pdo' => $pdo,
            'config' => $config,
        ];
    }

    public function handle(Request $request): never
    {
        if ($request->method() !== 'POST') {
            Response::error('method_not_allowed', 'Der MCP-Endpunkt akzeptiert nur POST.', 405, ['Allow' => 'POST']);
        }
        if (!self::isOriginAllowed($request)) {
            Response::error('forbidden_origin', 'Der Origin-Header gehört nicht zu dieser Website.', 403);
        }

        $pdo = Connection::open();
        $services = self::services($pdo, $this->config);
        /** @var TokenService $tokens */
        $tokens = $services['tokens'];
        $auth = McpAuth::authenticate($request, $tokens, new TokenIssuer($pdo, $this->config));
        if ($auth === null) {
            Response::error(
                'unauthorized',
                'Gültiges MCP-Token im Header Authorization: Bearer … erforderlich.',
                401,
                ['WWW-Authenticate' => Discovery::challengeHeader($this->config, $request->bearerToken() === null ? null : 'invalid_token')]
            );
        }

        $requestId = null;
        try {
            $message = self::parse($request->body());
            if (!array_key_exists('id', $message)) {
                Response::empty(202);
            }
            $requestId = $message['id'];
            $era = Protocol::negotiate($request, $message);
            $result = $this->dispatch($message, $era, $auth, $services);
            Response::raw(self::encode(['jsonrpc' => '2.0', 'id' => $requestId, 'result' => $result]), 200);
        } catch (ProtocolError $error) {
            Response::raw(self::encodeError($requestId, $error), $error->httpStatus, $error->headers);
        } catch (Throwable $error) {
            Log::error('MCP-Anfrage fehlgeschlagen', [
                'type' => $error::class,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]);
            $internal = new ProtocolError(ProtocolError::INTERNAL_ERROR, 'Interner Fehler.', 500);
            Response::raw(self::encodeError($requestId, $internal), 500);
        }
    }

    /** Ein Origin-Header (Browser) muss zum selben Host gehören; Claude Code sendet keinen. */
    private static function isOriginAllowed(Request $request): bool
    {
        $origin = $request->header('Origin');
        if ($origin === null) {
            return true;
        }
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        if (!is_string($originHost)) {
            return false;
        }
        $ownHost = (string) $request->header('Host');
        $originAuthority = $originPort === null ? $originHost : $originHost . ':' . $originPort;

        return strcasecmp($originAuthority, $ownHost) === 0;
    }

    /** @return array<string, mixed> */
    private static function parse(string $body): array
    {
        $message = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProtocolError(ProtocolError::PARSE_ERROR, 'Der Body ist kein gültiges JSON.');
        }
        if (!is_array($message) || array_is_list($message)) {
            throw new ProtocolError(ProtocolError::INVALID_REQUEST, 'Erwartet wird genau eine JSON-RPC-Nachricht als Objekt; Batches werden nicht unterstützt.');
        }
        if (!is_string($message['method'] ?? null)) {
            throw new ProtocolError(ProtocolError::INVALID_REQUEST, 'Das Feld „method“ fehlt oder ist kein Text.');
        }
        if (array_key_exists('id', $message) && !is_string($message['id']) && !is_int($message['id'])) {
            throw new ProtocolError(ProtocolError::INVALID_REQUEST, 'Das Feld „id“ muss Text oder ganze Zahl sein.');
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $message
     * @param array{version: string, modern: bool} $era
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @param array<string, mixed> $services
     * @return array<string, mixed>|stdClass
     */
    private function dispatch(array $message, array $era, array $auth, array $services): array|stdClass
    {
        $method = (string) $message['method'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        $result = match ($method) {
            'initialize' => $this->initialize($era['version']),
            'server/discover' => $this->discover($era),
            'ping' => new stdClass(),
            'tools/list' => $this->listTools($era, $auth, $services),
            'tools/call' => $this->callTool($params, $auth, $services),
            'resources/list' => $this->readOnlyList($auth, static fn (): array => ['resources' => self::resources($services)->list()], $era),
            'resources/templates/list' => $this->readOnlyList($auth, static fn (): array => ['resourceTemplates' => self::resources($services)->templates()], $era),
            'resources/read' => $this->readResource($params, $auth, $services, $era),
            'prompts/list' => $this->readOnlyList($auth, static fn (): array => ['prompts' => (new Prompts())->list()], $era),
            'prompts/get' => $this->getPrompt($params, $auth),
            default => throw new ProtocolError(
                ProtocolError::METHOD_NOT_FOUND,
                sprintf('Unbekannte Methode „%s“.', $method),
                $era['modern'] ? 404 : 200
            ),
        };

        if (!$era['modern'] || $result instanceof stdClass) {
            return $result;
        }

        return self::modernEnvelope($result);
    }

    /**
     * Bei Modern trägt jedes Ergebnis `resultType` und die Server-Info.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function modernEnvelope(array $result): array
    {
        $meta = $result['_meta'] ?? [];
        $meta[Protocol::META_SERVER_INFO] = ['name' => self::SERVER_NAME, 'version' => self::SERVER_VERSION];
        $result['_meta'] = $meta;

        return ['resultType' => 'complete'] + $result;
    }

    /** @return array<string, mixed> */
    private function initialize(string $version): array
    {
        return [
            'protocolVersion' => $version,
            'capabilities' => self::capabilities(),
            'serverInfo' => ['name' => self::SERVER_NAME, 'title' => self::SERVER_TITLE, 'version' => self::SERVER_VERSION],
            'instructions' => self::instructions(),
        ];
    }

    /**
     * @param array{version: string, modern: bool} $era
     * @return array<string, mixed>
     */
    private function discover(array $era): array
    {
        if (!$era['modern']) {
            throw new ProtocolError(ProtocolError::METHOD_NOT_FOUND, 'server/discover gibt es nur ab Protokoll ' . Protocol::MODERN . '; ältere Clients nutzen initialize.', 200);
        }

        return [
            'supportedVersions' => Protocol::supported(),
            'capabilities' => self::capabilities(),
            'instructions' => self::instructions(),
            'ttlMs' => self::CACHE_TTL_MS,
            'cacheScope' => 'public',
        ];
    }

    /**
     * @param array{version: string, modern: bool} $era
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @param array<string, mixed> $services
     * @return array<string, mixed>
     */
    private function listTools(array $era, array $auth, array $services): array
    {
        $tools = array_map(
            static fn (Tool $tool): array => $tool->describe(),
            self::registry($services)->visible($auth['scopes'])
        );

        return self::cacheable(['tools' => $tools], $era);
    }

    /**
     * @param array<string, mixed> $params
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @param array<string, mixed> $services
     * @return array<string, mixed>
     */
    private function callTool(array $params, array $auth, array $services): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];
        if (!is_string($name) || !is_array($arguments)) {
            throw new ProtocolError(ProtocolError::INVALID_PARAMS, 'tools/call braucht params.name (Text) und optional params.arguments (Objekt).');
        }

        return self::registry($services)->call($name, $arguments, $auth)->toArray();
    }

    /**
     * Resources und Prompts brauchen blog:read.
     *
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @param callable(): array<string, mixed> $list
     * @param array{version: string, modern: bool} $era
     * @return array<string, mixed>
     */
    private function readOnlyList(array $auth, callable $list, array $era): array
    {
        self::requireReadScope($auth);

        return self::cacheable($list(), $era);
    }

    /**
     * @param array<string, mixed> $params
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @param array<string, mixed> $services
     * @param array{version: string, modern: bool} $era
     * @return array<string, mixed>
     */
    private function readResource(array $params, array $auth, array $services, array $era): array
    {
        self::requireReadScope($auth);
        $uri = $params['uri'] ?? null;
        if (!is_string($uri) || $uri === '') {
            throw new ProtocolError(ProtocolError::INVALID_PARAMS, 'resources/read braucht params.uri (Text), z. B. "dispatch://style-guide".');
        }

        return self::cacheable(['contents' => self::resources($services)->read($uri)], $era);
    }

    /**
     * @param array<string, mixed> $params
     * @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth
     * @return array<string, mixed>
     */
    private function getPrompt(array $params, array $auth): array
    {
        self::requireReadScope($auth);
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];
        if (!is_string($name) || !is_array($arguments)) {
            throw new ProtocolError(ProtocolError::INVALID_PARAMS, 'prompts/get braucht params.name (Text) und optional params.arguments (Objekt).');
        }

        return (new Prompts())->get($name, $arguments);
    }

    /** @param array<string, mixed> $services */
    private static function resources(array $services): Resources
    {
        /** @var DocumentService $documents */
        $documents = $services['documents'];
        /** @var PostService $posts */
        $posts = $services['posts'];

        return new Resources($documents, $posts);
    }

    /** @param array{id: int, name: string, scopes: list<string>, kind: 'mcp'|'oauth'} $auth */
    private static function requireReadScope(array $auth): void
    {
        if (in_array(Scopes::READ, $auth['scopes'], true)) {
            return;
        }

        throw new ProtocolError(
            ProtocolError::INSUFFICIENT_SCOPE,
            'Resources und Prompts brauchen den Scope ' . Scopes::READ . '; das Token hat ihn nicht.',
            403,
            ['required_scope' => Scopes::READ],
            ['WWW-Authenticate' => sprintf('Bearer error="insufficient_scope", scope="%s"', Scopes::READ)]
        );
    }

    /**
     * Cache-Hinweise gibt es nur bei Modern; die Listen hängen vom Token ab, daher `private`.
     *
     * @param array<string, mixed> $result
     * @param array{version: string, modern: bool} $era
     * @return array<string, mixed>
     */
    private static function cacheable(array $result, array $era): array
    {
        if (!$era['modern']) {
            return $result;
        }

        return $result + ['ttlMs' => self::CACHE_TTL_MS, 'cacheScope' => 'private'];
    }

    /** @param array<string, mixed> $services */
    private static function registry(array $services): Registry
    {
        $registry = new Registry();
        BriefingTools::register($registry, $services);
        DocumentTools::register($registry, $services);
        CategoryTools::register($registry, $services);
        SettingTools::register($registry, $services);
        ChangeTools::register($registry, $services);
        QueueTools::register($registry, $services);
        PostTools::register($registry, $services);
        MediaTools::register($registry, $services);
        StatsTools::register($registry, $services);
        SiteTools::register($registry, $services);

        return $registry;
    }

    /** @return array<string, stdClass> */
    private static function capabilities(): array
    {
        return ['tools' => new stdClass(), 'resources' => new stdClass(), 'prompts' => new stdClass()];
    }

    private static function instructions(): string
    {
        return trim((string) file_get_contents(dirname(__DIR__, 2) . '/mcp/instructions.md'));
    }

    /** @param array<string, mixed> $body */
    private static function encode(array $body): string
    {
        return json_encode($body, self::JSON_FLAGS);
    }

    private static function encodeError(string|int|null $requestId, ProtocolError $error): string
    {
        $payload = ['code' => $error->rpcCode, 'message' => $error->getMessage()];
        if ($error->data !== []) {
            $payload['data'] = $error->data;
        }

        return self::encode(['jsonrpc' => '2.0', 'id' => $requestId, 'error' => $payload]);
    }
}
