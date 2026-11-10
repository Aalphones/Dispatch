<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Db\Migrator;
use Dispatch\Domain\Actor;
use Dispatch\Domain\Seeder;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Site\Builder;
use Dispatch\Util\Env;
use Dispatch\Util\Log;
use PDO;
use Throwable;

final class DeployController
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

        $pdo = $this->step('datenbank', static fn (): PDO => Connection::open());

        $migrations = $this->step('migrationen', fn (): array => (new Migrator($pdo, (string) $this->config['app_dir'] . '/migrations'))->run());

        $seeded = $this->step('startinhalte', fn (): array => (new Seeder($pdo, $this->config))->run(Actor::deploy()));

        $built = $this->step('bauen', fn (): array => (new Builder($this->config))->buildPlaceholder());

        Response::json(['migrations' => $migrations, 'seeded' => $seeded, 'built' => $built]);
    }

    /**
     * @template T
     * @param callable(): T $action
     * @return T
     */
    private function step(string $name, callable $action): mixed
    {
        try {
            return $action();
        } catch (Throwable $exception) {
            // Der Ausnahme-Text kann Host oder Benutzer enthalten: nur ins Log, nie in die Antwort.
            Log::error('Deploy-Schritt fehlgeschlagen', [
                'step' => $name,
                'type' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            Response::error('deploy_failed', 'Schritt ' . $name . ' fehlgeschlagen, Details im Server-Log.', 500);
        }
    }
}
