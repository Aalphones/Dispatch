<?php

declare(strict_types=1);

use Dispatch\Http\Response;
use Dispatch\Util\Env;
use Dispatch\Util\Log;

error_reporting(E_ALL);
ini_set('display_errors', '0');

spl_autoload_register(static function (string $className): void {
    $prefix = 'Dispatch\\';
    if (!str_starts_with($className, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(getenv('DISPATCH_ENV_FILE') ?: __DIR__ . '/.env');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    Log::error('Unbehandelte Ausnahme', [
        'type' => $exception::class,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    Response::error('server_error', 'Interner Serverfehler.', 500);
});

// Ohne SSH ist die Logdatei die einzige Spur: Fatale Fehler (Speicher, Zeitlimit)
// laufen an beiden Handlern oben vorbei und kommen nur hier an.
register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    if ($lastError === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($lastError['type'], $fatalTypes, true)) {
        return;
    }

    Log::error('Schwerer Fehler', [
        'message' => $lastError['message'],
        'file' => $lastError['file'],
        'line' => $lastError['line'],
    ]);

    if (!headers_sent()) {
        Response::error('server_error', 'Interner Serverfehler.', 500);
    }
});

return require __DIR__ . '/config.php';
