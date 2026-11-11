<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Domain\DomainError;
use Dispatch\Http\Response;

/** Übersetzt fachliche Fehler in die Antwort der Redaktions-API: Eingabefehler 400, Unbekanntes 404, alles andere 409. */
final class ApiErrors
{
    private const BAD_REQUEST = ['invalid_value', 'invalid_json', 'invalid_field'];

    public static function respond(DomainError $error): never
    {
        $status = 409;
        if (in_array($error->errorCode, self::BAD_REQUEST, true)) {
            $status = 400;
        }
        if ($error->errorCode === 'not_found') {
            $status = 404;
        }

        $body = ['error' => $error->errorCode, 'message' => $error->getMessage()];
        if ($error->details !== []) {
            $body['details'] = $error->details;
        }

        Response::json($body, $status);
    }
}
