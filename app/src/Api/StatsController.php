<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\StatsService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** Zugriffsstatistik für den Redaktionsmodus (ADR-021). */
final class StatsController
{
    private const DEFAULT_DAYS = 14;
    private const DEFAULT_POST_DAYS = 30;

    public function overview(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $report = (new StatsService(Connection::open()))->report($this->days($request, self::DEFAULT_DAYS));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($report);
    }

    public function post(Request $request, int $id): never
    {
        ApiAuth::owner($request);

        try {
            $report = (new StatsService(Connection::open()))->postReport($id, $this->days($request, self::DEFAULT_POST_DAYS));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($report);
    }

    /** @throws DomainError wenn `days` keine ganze Zahl ist; den Bereich prüft der Dienst */
    private function days(Request $request, int $default): int
    {
        $value = $request->query('days');
        if ($value === null) {
            return $default;
        }
        if (preg_match('/^[0-9]{1,4}$/', $value) !== 1) {
            throw new DomainError('invalid_value', 'days muss eine ganze Zahl von 1 bis 365 sein.', ['field' => 'days']);
        }

        return (int) $value;
    }
}
