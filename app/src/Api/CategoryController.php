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
use Dispatch\Util\Text;

/**
 * Kategorie anlegen: mit dem Deploy-Token für `bin/dispatch category:create --remote` (wer ihn hat, handelt als Inhaber)
 * oder per Anmeldung aus dem Redaktionsmodus; Liste, Interessen, Umbenennen und Löschen nur per Anmeldung.
 */
final class CategoryController
{
    /** Was der Inhaber im Redaktionsmodus direkt ändert; Slug und Beschreibung bleiben fest. */
    private const OWNER_PATCH_FIELDS = [...CategoryService::INTERNAL_FIELDS, 'name'];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        if (DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'))) {
            $this->createWithDeployToken();
        }

        $actor = ApiAuth::owner($request);
        try {
            $fields = $this->withSlugFromName($request->json());
            $pdo = Connection::open();
            $result = (new CategoryService($pdo, $this->config))->create($fields, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($result + Rebuild::run($this->config, $pdo), 201);
    }

    private function createWithDeployToken(): never
    {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            Response::error('invalid_value', 'Der Body muss ein JSON-Objekt sein, z. B. {"slug":"technik","name":"Technik"}.', 400);
        }

        try {
            $result = (new CategoryService(Connection::open(), $this->config))->create($this->withSlugFromName($body), Actor::owner());
        } catch (DomainError $error) {
            Response::error($error->errorCode, $error->getMessage(), 400);
        }

        Response::json($result, 201);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function withSlugFromName(array $fields): array
    {
        if (!array_key_exists('slug', $fields) && is_string($fields['name'] ?? null) && trim($fields['name']) !== '') {
            $fields['slug'] = Text::slugify($fields['name']);
        }

        return $fields;
    }

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        Response::json(['categories' => (new CategoryService(Connection::open(), $this->config))->overview()]);
    }

    /** Interessen und Anzeigename des Inhabers, sofort wirksam. */
    public function patch(Request $request, int $id): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $fields = $request->json();
            foreach (array_keys($fields) as $field) {
                if (!in_array($field, self::OWNER_PATCH_FIELDS, true)) {
                    throw new DomainError(
                        'invalid_field',
                        sprintf('Das Feld „%s“ lässt sich hier nicht ändern. Erlaubt: %s.', (string) $field, implode(', ', self::OWNER_PATCH_FIELDS)),
                        ['field' => (string) $field]
                    );
                }
            }
            if ($fields === []) {
                throw new DomainError('invalid_value', 'Der Body braucht mindestens eines von: ' . implode(', ', self::OWNER_PATCH_FIELDS) . '.');
            }

            $pdo = Connection::open();
            $service = new CategoryService($pdo, $this->config);
            $service->update($id, $fields, $actor);
            $category = $service->describe($id);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        $response = ['category' => $category];
        if (array_key_exists('name', $fields)) {
            $response += Rebuild::run($this->config, $pdo);
        }

        Response::json($response);
    }

    /** Body optional: `{"move_to": <id>}` nennt die Ersatz-Kategorie für zugeordnete Artikel. */
    public function delete(Request $request, int $id): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $moveTo = $this->moveToFrom($request);
            $pdo = Connection::open();
            $result = (new CategoryService($pdo, $this->config))->delete($id, $moveTo, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($result + Rebuild::run($this->config, $pdo));
    }

    private function moveToFrom(Request $request): ?int
    {
        if (trim($request->body()) === '') {
            return null;
        }

        $moveTo = $request->json()['move_to'] ?? null;
        if ($moveTo !== null && !is_int($moveTo)) {
            throw new DomainError('invalid_value', 'Das Feld „move_to“ muss die ID einer Kategorie oder null sein.', ['field' => 'move_to']);
        }

        return $moveTo;
    }
}
