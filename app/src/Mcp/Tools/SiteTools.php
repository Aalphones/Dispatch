<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;
use Dispatch\Site\Builder;
use PDO;

final class SiteTools
{
    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var PDO $pdo */
        $pdo = $services['pdo'];
        /** @var array<string, mixed> $config */
        $config = $services['config'];

        $registry->add(new Tool(
            'rebuild_site',
            'Website neu bauen',
            'Schreibt die öffentliche Website aus den freigegebenen Inhalten neu; unveränderte Dateien bleiben liegen. Es erscheint nichts, was der Inhaber nicht freigegeben hat — das Tool ist nur nötig, wenn du den Stand der Website prüfen willst. Läuft gerade ein anderer Build, kommt der Fehler build_busy; dann in einer Minute erneut versuchen. Ergebnis: written, unchanged, deleted, ms.',
            Schema::object(),
            Tool::annotations('Website neu bauen', 'W'),
            Scopes::WRITE,
            static function (array $args, Actor $actor) use ($config, $pdo): ToolResult {
                return ToolResult::json((new Builder($config, $pdo))->build());
            }
        ));
    }
}
