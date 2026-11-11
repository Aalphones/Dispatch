<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\ChangeRepository;
use Dispatch\Domain\PostRepository;
use Dispatch\Domain\QueueRepository;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** Zähler für das Konto-Menü des Redaktionsmodus. */
final class OverviewController
{
    public function show(Request $request): never
    {
        ApiAuth::owner($request);
        $pdo = Connection::open();
        $posts = new PostRepository($pdo);

        Response::json([
            'review' => $posts->countByStatus()['review'] ?? 0,
            'returned' => $posts->countReturned(),
            'changes' => count((new ChangeRepository($pdo))->list('pending')),
            'queue_open' => (new QueueRepository($pdo))->countByEffectiveStatus()['open'] ?? 0,
        ]);
    }
}
