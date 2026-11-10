<?php

declare(strict_types=1);

namespace Dispatch\Mcp\Tools;

use Dispatch\Auth\Scopes;
use Dispatch\Domain\Actor;
use Dispatch\Domain\ChangeService;
use Dispatch\Domain\DomainError;
use Dispatch\Mcp\Registry;
use Dispatch\Mcp\Schema;
use Dispatch\Mcp\Tool;
use Dispatch\Mcp\ToolResult;

final class ChangeTools
{
    private const STATUSES = ['pending', 'applied', 'rejected', 'all'];

    /** @param array<string, mixed> $services */
    public static function register(Registry $registry, array $services): void
    {
        /** @var ChangeService $changes */
        $changes = $services['changes'];

        $registry->add(new Tool(
            'list_changes',
            'Änderungsvorschläge ansehen',
            'Zeigt Änderungsvorschläge mit Vorher-Wert und Status: offen (pending), übernommen (applied) oder abgelehnt (rejected). Nutze es nach update_settings, update_document, save_category oder update_media, um zu sehen, ob der Inhaber den Vorschlag schon entschieden hat. Beispiel: {"status": "all"}.',
            Schema::object(['status' => Schema::choice(self::STATUSES, 'Standard pending')]),
            Tool::annotations('Änderungsvorschläge ansehen', 'R'),
            Scopes::READ,
            static function (array $args, Actor $actor) use ($changes): ToolResult {
                $status = (string) ($args['status'] ?? 'pending');
                if (!in_array($status, self::STATUSES, true)) {
                    throw new DomainError('invalid_value', sprintf('Unbekannter Status „%s“. Gültig: %s.', $status, implode(', ', self::STATUSES)), ['field' => 'status']);
                }

                return ToolResult::json(['changes' => $changes->list($status === 'all' ? null : $status)]);
            }
        ));
    }
}
