<?php

declare(strict_types=1);

use Dispatch\Api\AuthController;
use Dispatch\Api\CategoryController;
use Dispatch\Api\DeployController;
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
// Der MCP-Endpunkt antwortet in JSON-RPC und prüft Origin und Anmeldung selbst.
$router->add('POST', '/dispatch/mcp', static fn () => (new McpController($config))->handle($request));

$router->dispatch($request);
