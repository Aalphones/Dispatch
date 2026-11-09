<?php

declare(strict_types=1);

namespace Dispatch\Db;

use PDO;

// Bewusst ohne Transaktion: MySQL committet DDL implizit, ein späteres commit() würde werfen.
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory
    ) {
    }

    /** @return list<string> Namen der neu angewandten Dateien */
    public function run(): array
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'filename VARCHAR(190) NOT NULL PRIMARY KEY, '
            . 'applied_at DATETIME NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $appliedNames = $this->pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($this->directory . '/*.sql');
        if ($files === false) {
            $files = [];
        }
        sort($files);

        $newlyApplied = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $appliedNames, true)) {
                continue;
            }

            $this->execute((string) file_get_contents($file));

            $insert = $this->pdo->prepare('INSERT INTO schema_migrations (filename, applied_at) VALUES (?, UTC_TIMESTAMP())');
            $insert->execute([$filename]);
            $newlyApplied[] = $filename;
        }

        return $newlyApplied;
    }

    // Einzeln statt Mehrfach-exec: MySQL verschluckt dabei Fehler nach der ersten Anweisung.
    private function execute(string $sql): void
    {
        $statements = preg_split('/;\s*(\r?\n|$)/', $sql);
        if ($statements === false) {
            return;
        }

        foreach ($statements as $statement) {
            $trimmedStatement = trim($statement);
            if ($trimmedStatement === '') {
                continue;
            }

            $this->pdo->exec($trimmedStatement);
        }
    }
}
