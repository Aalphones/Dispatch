<?php

declare(strict_types=1);

// Brücke im ausgelieferten Bereich. Der Code liegt als Nachbarordner daneben
// (www/dispatch-app/, gesperrt durch dessen .htaccess) und wird per Dateizugriff eingebunden.
// Diese Datei landet auf dem Server unter www/dispatch/index.php (ADR-002).
require __DIR__ . '/../dispatch-app/index.php';
