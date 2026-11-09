<?php

declare(strict_types=1);

use Dispatch\Api\DeployController;
use Dispatch\Api\DiagController;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

$config = require __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

// Pfad → [erlaubte Methode, Handler]; bekannter Pfad mit anderer Methode ergibt 405.
$routes = [
    '/dispatch/api/deploy' => ['POST', static fn () => (new DeployController($config))->handle($request)],
    '/dispatch/api/diag' => ['GET', static fn () => (new DiagController($config))->handle($request)],
];

if (isset($routes[$request->path()])) {
    [$allowedMethod, $handler] = $routes[$request->path()];
    if ($request->method() !== $allowedMethod) {
        Response::error('method_not_allowed', 'Methode nicht erlaubt.', 405, ['Allow' => $allowedMethod]);
    }
    $handler();
}

match (true) {
    $request->path() === '/dispatch/mcp' => Response::error(
        'unauthorized',
        'MCP folgt in Meilenstein 3.',
        401,
        ['WWW-Authenticate' => 'Bearer']
    ),
    default => Response::error('not_found', 'Diesen Pfad gibt es hier nicht.', 404),
};
