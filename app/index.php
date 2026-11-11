<?php

declare(strict_types=1);

use Dispatch\Api\AuthController;
use Dispatch\Api\CategoryController;
use Dispatch\Api\ChangeController;
use Dispatch\Api\DeployController;
use Dispatch\Api\OverviewController;
use Dispatch\Api\PostController;
use Dispatch\Api\PreviewController;
use Dispatch\Api\TokenController;
use Dispatch\Http\Request;
use Dispatch\Http\Router;
use Dispatch\Mcp\McpController;

$config = require __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

$router = new Router();
$router->add('POST', '/dispatch/api/deploy', static fn () => (new DeployController($config))->handle($request));
$router->add('POST', '/dispatch/api/categories', static fn () => (new CategoryController($config))->handle($request));
$router->add('POST', '/dispatch/api/tokens', static fn () => (new TokenController())->handle($request));
$router->add('POST', '/dispatch/api/auth/login', static fn () => (new AuthController())->login($request));
$router->add('POST', '/dispatch/api/auth/refresh', static fn () => (new AuthController())->refresh($request));
$router->add('POST', '/dispatch/api/auth/logout', static fn () => (new AuthController())->logout($request));
$router->add('POST', '/dispatch/api/auth/logout-all', static fn () => (new AuthController())->logoutAll($request));
$router->add('GET', '/dispatch/api/overview', static fn () => (new OverviewController())->show($request));
$router->add('GET', '/dispatch/api/posts', static fn () => (new PostController($config))->list($request));
$router->add('GET', '/dispatch/api/posts/by-slug/{slug}', static fn (array $params) => (new PostController($config))->bySlug($request, (string) $params['slug']));
$router->add('GET', '/dispatch/api/posts/{id}/preview', static fn (array $params) => (new PreviewController($config))->byLogin($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/posts/{id}/diff', static fn (array $params) => (new PostController($config))->diff($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/preview',static fn () => (new PreviewController($config))->bySignature($request));
$router->add('POST', '/dispatch/api/posts/{id}/approve', static fn (array $params) => (new PostController($config))->approve($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/posts/{id}/return', static fn (array $params) => (new PostController($config))->return($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/posts/{id}/unpublish', static fn (array $params) => (new PostController($config))->unpublish($request, (int) $params['id']));
$router->add('DELETE', '/dispatch/api/posts/{id}', static fn (array $params) => (new PostController($config))->discard($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/changes', static fn () => (new ChangeController($config))->list($request));
$router->add('POST', '/dispatch/api/changes/{id}/apply', static fn (array $params) => (new ChangeController($config))->apply($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/changes/{id}/reject', static fn (array $params) => (new ChangeController($config))->reject($request, (int) $params['id']));
// Der MCP-Endpunkt antwortet in JSON-RPC und prüft Origin und Anmeldung selbst.
$router->add('POST', '/dispatch/mcp', static fn () => (new McpController($config))->handle($request));

$router->dispatch($request);
