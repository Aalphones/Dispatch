<?php

declare(strict_types=1);

namespace Dispatch\Db;

use PDO;
use Throwable;

final class Transaction
{
    /**
     * Läuft in der laufenden Transaktion mit, sonst in einer neuen. So bleibt eine Fach-Änderung,
     * die ein anderer Service in seiner Transaktion aufruft (Vorschlag übernehmen), atomar.
     *
     * @param callable(): mixed $work
     */
    public static function join(PDO $pdo, callable $work): mixed
    {
        if ($pdo->inTransaction()) {
            return $work();
        }

        return self::run($pdo, $work);
    }

    /**
     * Nur für DML — DDL committet in MySQL implizit (Migrator läuft deshalb ohne Transaktion).
     *
     * @param callable(): mixed $work
     */
    public static function run(PDO $pdo, callable $work): mixed
    {
        $pdo->beginTransaction();

        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
