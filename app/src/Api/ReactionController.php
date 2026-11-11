<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\ReactionService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** „Mehr davon / Weniger“ des Inhabers (ADR-019). */
final class ReactionController
{
    public function set(Request $request, int $postId): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $body = $request->json();
            if (!array_key_exists('reaction', $body)) {
                throw new DomainError('invalid_value', 'reaction fehlt („more“, „less“ oder null zum Löschen).', ['field' => 'reaction']);
            }
            $reaction = $body['reaction'];
            $note = $body['note'] ?? null;
            if ($reaction !== null && !is_string($reaction)) {
                throw new DomainError('invalid_value', 'reaction muss „more“, „less“ oder null sein.', ['field' => 'reaction']);
            }
            if ($note !== null && !is_string($note)) {
                throw new DomainError('invalid_value', 'note muss ein Text sein.', ['field' => 'note']);
            }

            (new ReactionService(Connection::open()))->set($postId, $reaction, $note, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::empty(204);
    }

    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        // Als Objekt kodieren: ohne Reaktionen soll `{}` herauskommen, nicht `[]`.
        Response::json(['reactions' => (object) (new ReactionService(Connection::open()))->all()]);
    }
}
