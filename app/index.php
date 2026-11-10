<?php

declare(strict_types=1);

use Dispatch\Api\DeployController;
use Dispatch\Api\TokenController;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Mcp\McpController;

$config = require __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

// Pfad → [erlaubte Methode, Handler]; bekannter Pfad mit anderer Methode ergibt 405.
$routes = [
    '/dispatch/api/deploy' => ['POST', static fn () => (new DeployController($config))->handle($request)],
    '/dispatch/api/tokens' => ['POST', static fn () => (new TokenController())->handle($request)],
];

if (isset($routes[$request->path()])) {
    [$allowedMethod, $handler] = $routes[$request->path()];
    if ($request->method() !== $allowedMethod) {
        Response::error('method_not_allowed', 'Methode nicht erlaubt.', 405, ['Allow' => $allowedMethod]);
    }
    $handler();
}

// Der MCP-Controller prüft die Methode selbst (405 mit Allow: POST) und antwortet in JSON-RPC.
if ($request->path() === '/dispatch/mcp') {
    (new McpController($config))->handle($request);
}

Response::error('not_found', 'Diesen Pfad gibt es hier nicht.', 404);
