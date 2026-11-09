<?php

declare(strict_types=1);

namespace Dispatch\Db;

use Dispatch\Util\Env;
use PDO;

final class Connection
{
    public static function open(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Env::get('DISPATCH_DB_HOST'),
            Env::get('DISPATCH_DB_NAME')
        );

        $pdo = new PDO($dsn, Env::get('DISPATCH_DB_USER'), Env::get('DISPATCH_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
