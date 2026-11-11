<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Db\Connection;
use Dispatch\Domain\StatsRecorder;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;
use Dispatch\Util\Log;
use Throwable;

/** Zähl-Aufruf der Statistik (ADR-022): antwortet immer 204, auch wenn nichts gezählt wird. */
final class HitController
{
    private const MAX_BODY_BYTES = 2048;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        $beacon = $this->acceptedBeacon($request);        if ($beacon !== null) {
            try {
                (new StatsRecorder(Connection::open(), $this->config))->record($beacon, $request->ip(), (string) $request->header('User-Agent'));
            } catch (Throwable $error) {
                // Eine fehlgeschlagene Zählung darf die Seite nie stören; sie landet nur im Protokoll.
                Log::error('Zähl-Aufruf fehlgeschlagen', ['error' => $error->getMessage()]);
            }
        }

        Response::empty(204);
    }

    /** @return array<string, mixed>|null null, wenn der Aufruf verworfen wird */
    private function acceptedBeacon(Request $request): ?array
    {
        if ($this->config['stats_enabled'] !== true || $this->isRejectedSender($request)) {
            return null;
        }

        $body = $request->body();
        if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
            return null;
        }
        $beacon = json_decode($body, true);

        return is_array($beacon) && !array_is_list($beacon) ? $beacon : null;
    }

    private function isRejectedSender(Request $request): bool
    {
        $userAgent = (string) $request->header('User-Agent');
        if ($userAgent === '' || preg_match((string) $this->config['stats_bot_pattern'], $userAgent) === 1) {
            return true;
        }
        if ($request->header('DNT') === '1' || $request->header('Sec-GPC') === '1') {
            return true;
        }

        return $request->header('Origin') !== $this->ownOrigin();
    }

    private function ownOrigin(): string
    {
        $parts = parse_url(Env::get('DISPATCH_BASE_URL'));
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return ($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '') . $port;
    }
}
