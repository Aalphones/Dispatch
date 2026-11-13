<?php

declare(strict_types=1);

namespace Dispatch\Api;

use DateTimeImmutable;
use DateTimeZone;
use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\ScheduleService;
use Dispatch\Domain\SettingService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use PDO;

/** Veröffentlichungsplan lesen und das Tageslimit setzen (ADR-028). */
final class ScheduleController
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function show(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $plan = $this->plan(Connection::open());
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($plan);
    }

    public function patch(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $input = $request->json();
            if (!array_key_exists('per_day', $input)) {
                throw new DomainError('invalid_value', 'per_day fehlt (Ganzzahl 1 bis 6: Artikel pro Tag aus der Warteschlange).', ['field' => 'per_day']);
            }

            $pdo = Connection::open();
            (new SettingService($pdo, $this->config))->set(['release_per_day' => $input['per_day']], Actor::owner());
            $plan = $this->plan($pdo);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($plan);
    }

    /** @return array<string, mixed> */
    private function plan(PDO $pdo): array
    {
        return (new ScheduleService($pdo, $this->config))->plan(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }
}
