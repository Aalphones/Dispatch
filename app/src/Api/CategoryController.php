<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\CategoryService;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;

/**
 * Kategorie anlegen für `bin/dispatch category:create --remote`, geschützt mit dem Deploy-Token
 * (wer ihn hat, handelt als Inhaber); Liste und Interessen für den Redaktionsmodus per Anmeldung.
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

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        Response::json(['categories' => (new CategoryService(Connection::open(), $this->config))->overview()]);
    }

    /** Interessen des Inhabers: nur die internen Felder, sofort wirksam. */
    public function patch(Request $request, int $id): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $fields = $request->json();
            foreach (array_keys($fields) as $field) {
                if (!in_array($field, CategoryService::INTERNAL_FIELDS, true)) {
                    throw new DomainError(
                        'invalid_field',
                        sprintf('Das Feld „%s“ lässt sich hier nicht ändern. Erlaubt: %s.', (string) $field, implode(', ', CategoryService::INTERNAL_FIELDS)),
                        ['field' => (string) $field]
                    );
                }
            }
            if ($fields === []) {
                throw new DomainError('invalid_value', 'Der Body braucht mindestens eines von: ' . implode(', ', CategoryService::INTERNAL_FIELDS) . '.');
            }

            $service = new CategoryService(Connection::open(), $this->config);
            $service->update($id, $fields, $actor);
            $category = $service->describe($id);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['category' => $category]);
    }
}
