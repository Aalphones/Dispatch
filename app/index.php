<?php

declare(strict_types=1);

use Dispatch\Http\Request;
use Dispatch\Http\Response;

$config = require __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

match (true) {
    $request->path() === '/dispatch/mcp' => Response::error(
        'unauthorized',
        'MCP folgt in Meilenstein 3.',
        401,
        ['WWW-Authenticate' => 'Bearer']
    ),
    default => Response::error('not_found', 'Diesen Pfad gibt es hier nicht.', 404),
};
