<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\CategoryRestructureService;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/**
 * `POST /dispatch/api/categories/restructure` (ADR-037): `{"mode": "dry-run" | "apply" | "rollback", "backup": {…}}`.
 * Deploy-Token (für `bin/dispatch category:restructure --remote`) oder Anmeldung des Inhabers.
 */
final class CategoryRestructureController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        $actor = ApiAuth::ownerOrDeploy($request);

        try {
            $body = $request->json();
            $mode = $body['mode'] ?? null;
            $pdo = Connection::open();
            $service = new CategoryRestructureService($pdo, $this->config);

            $result = match ($mode) {
                'dry-run' => ['plan' => $service->plan()],
                'apply' => $service->apply($actor),
                'rollback' => ['summary' => $service->rollback($this->backupFrom($body), $actor)],
                default => throw new DomainError('invalid_value', 'Das Feld „mode“ muss dry-run, apply oder rollback sein.', ['field' => 'mode']),
            };
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        if ($mode !== 'dry-run') {
            $result += Rebuild::run($this->config, $pdo);
        }

        Response::json($result);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function backupFrom(array $body): array
    {
        $backup = $body['backup'] ?? null;
        if (!is_array($backup)) {
            throw new DomainError('invalid_value', 'Zum Zurücksetzen gehört das Feld „backup“ mit dem Inhalt der Sicherungsdatei.', ['field' => 'backup']);
        }

        return $backup;
    }
}
