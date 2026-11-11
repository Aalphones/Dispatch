<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\ChangeService;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** Änderungsvorschläge der Agenten: ansehen, übernehmen, ablehnen — nur der Inhaber (ADR-021). */
final class ChangeController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        $changes = [];
        foreach ($this->service()->list('pending') as $change) {
            $changes[] = [
                'id' => $change['id'],
                'entity' => $change['entity'],
                'key' => $change['entity_key'],
                'payload' => $change['payload'],
                'before' => $change['before'],
                'created_at' => $change['created_at'],
                'actor' => $change['created_by'],
            ];
        }

        Response::json(['changes' => $changes]);
    }

    public function apply(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $this->service()->apply($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['applied' => $id] + Rebuild::run($this->config, Connection::open()));
    }

    public function reject(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $this->service()->reject($id, Actor::owner());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }

    private function service(): ChangeService
    {
        return new ChangeService(Connection::open(), $this->config);
    }
}
