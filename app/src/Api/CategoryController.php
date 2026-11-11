<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\CategoryService;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/**
 * Kategorie anlegen für `bin/dispatch category:create --remote`, geschützt mit dem Deploy-Token.
 * Wer den Deploy-Token hat, handelt als Inhaber; bis zur Verwaltungsoberfläche (Meilenstein 4)
 * ist das der einzige Weg, auf dem Server eine Kategorie ohne Datenbankzugriff anzulegen.
 */
final class CategoryController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        if (!DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'))) {
            Response::error('unauthorized', 'Deploy-Token fehlt oder ist falsch.', 401);
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            Response::error('invalid_value', 'Der Body muss ein JSON-Objekt sein, z. B. {"slug":"technik","name":"Technik"}.', 400);
        }

        try {
            $result = (new CategoryService(Connection::open(), $this->config))->create($body, Actor::owner());
        } catch (DomainError $error) {
            Response::error($error->errorCode, $error->getMessage(), 400);
        }

        Response::json($result, 201);
    }
}
