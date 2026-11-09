<?php

declare(strict_types=1);

// Router für den lokalen PHP-Server (wird nicht hochgeladen). Start:
//   php -S localhost:8080 -t public dev-router.php
// Bildet Brücke (/dispatch/) und Sperre (/dispatch-app/) nach; alles andere kommt als Datei aus public/.

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if (str_starts_with($path, '/dispatch/') || str_starts_with($path, '/.well-known/oauth-')) {
    // Nur dieser Router setzt die Variable: lokal gilt app/.env.local, nie app/.env (ADR-006).
    putenv('DISPATCH_ENV_FILE=' . __DIR__ . '/app/.env.local');
    require __DIR__ . '/app/index.php';

    return true;
}

if (str_starts_with($path, '/dispatch-app/')) {
    http_response_code(403);

    return true;
}

return false;
