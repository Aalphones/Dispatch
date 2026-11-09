<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\DeployToken;
use Dispatch\Db\Connection;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Util\Env;
use PDO;
use Throwable;

// Messsonde für Meilenstein 0; wird am Ende von Phase 5 entfernt. Gibt nie Werte aus .env aus, nur ob sie gesetzt sind.
final class DiagController
{
    private const ENV_KEYS = [
        'DISPATCH_BASE_URL',
        'DISPATCH_DB_HOST',
        'DISPATCH_DB_NAME',
        'DISPATCH_DB_USER',
        'DISPATCH_DB_PASSWORD',
        'DISPATCH_DEPLOY_TOKEN',
    ];

    private const EXTENSIONS = ['pdo_mysql', 'curl', 'openssl', 'fileinfo', 'mbstring', 'intl', 'dom', 'gd', 'exif'];

    private const OUTBOUND_URL = 'https://example.com/';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function handle(Request $request): never
    {
        if (!DeployToken::matches($request, Env::get('DISPATCH_DEPLOY_TOKEN'))) {
            Response::error('unauthorized', 'Deploy-Token fehlt oder ist falsch.', 401);
        }

        $checks = [
            'php_version' => ['ok' => version_compare(PHP_VERSION, '8.5.0', '>='), 'value' => PHP_VERSION],
            'sapi' => ['ok' => true, 'value' => PHP_SAPI],
            'extensions' => $this->extensions(),
            'gd_webp' => $this->gdWebp(),
            'authorization_header' => $this->authorizationHeader(),
            'env' => $this->env(),
            'mysql' => $this->mysql(),
            'outbound_https' => $this->outboundHttps(),
            'limits' => $this->limits(),
            'open_basedir' => ['ok' => true, 'value' => ini_get('open_basedir')],
            'web_root' => $this->webRoot(),
            'web_root_writable' => $this->writable((string) $this->config['web_root']),
            'storage_writable' => $this->writable((string) $this->config['storage_dir'] . '/logs'),
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if ($check['ok'] === false) {
                $allOk = false;
            }
        }

        Response::json(['all_ok' => $allOk, 'checks' => $checks]);
    }

    /** @return array{ok: bool, value: array<string, bool>} */
    private function extensions(): array
    {
        $loaded = [];
        foreach (self::EXTENSIONS as $extension) {
            $loaded[$extension] = extension_loaded($extension);
        }

        $isOk = true;
        foreach ($loaded as $extension => $isLoaded) {
            if ($extension !== 'exif' && !$isLoaded) {
                $isOk = false;
            }
        }

        return ['ok' => $isOk, 'value' => $loaded];
    }

    /** @return array{ok: bool, value: bool} */
    private function gdWebp(): array
    {
        $hasWebp = function_exists('imagewebp') && function_exists('gd_info') && (gd_info()['WebP Support'] ?? false);

        return ['ok' => $hasWebp, 'value' => $hasWebp];
    }

    /** @return array{ok: bool, value: array<string, bool>} */
    private function authorizationHeader(): array
    {
        $seen = [
            'HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']),
            'REDIRECT_HTTP_AUTHORIZATION' => isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']),
            'getallheaders' => false,
        ];

        if (function_exists('getallheaders')) {
            foreach (array_keys(getallheaders()) as $headerName) {
                if (strtolower((string) $headerName) === 'authorization') {
                    $seen['getallheaders'] = true;
                }
            }
        }

        return ['ok' => in_array(true, $seen, true), 'value' => $seen];
    }

    /** @return array{ok: bool, value: array<string, bool>} */
    private function env(): array
    {
        $isSet = [];
        foreach (self::ENV_KEYS as $key) {
            $isSet[$key] = Env::get($key) !== '';
        }

        return ['ok' => !in_array(false, $isSet, true), 'value' => $isSet];
    }

    /** @return array{ok: bool, value: array<string, mixed>|string} */
    private function mysql(): array
    {
        try {
            $pdo = Connection::open();
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $support = $pdo->query("SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB'")->fetchColumn();
            $hasInnodb = in_array($support, ['YES', 'DEFAULT'], true);

            return ['ok' => $hasInnodb, 'value' => ['version' => $version, 'innodb' => $support === false ? 'NO' : $support]];
        } catch (Throwable $exception) {
            // Nur die Ausnahmeklasse: der Text kann Host oder Benutzer enthalten.
            return ['ok' => false, 'value' => 'Verbindung fehlgeschlagen (' . $exception::class . ')'];
        }
    }

    /** @return array{ok: bool, value: int|string} */
    private function outboundHttps(): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'value' => 'curl nicht geladen'];
        }

        $handle = curl_init(self::OUTBOUND_URL);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        // Kein curl_close: ab PHP 8.5 veraltet und im Fehler-Handler eine Ausnahme; das Handle wird automatisch freigegeben.

        if ($status === 0) {
            return ['ok' => false, 'value' => $curlError];
        }

        return ['ok' => $status === 200, 'value' => $status];
    }

    /** @return array{ok: bool, value: array<string, mixed>} */
    private function limits(): array
    {
        return [
            'ok' => true,
            'value' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'user_ini.filename' => ini_get('user_ini.filename'),
            ],
        ];
    }

    /** @return array{ok: bool, value: array<string, string|false>} */
    private function webRoot(): array
    {
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $webRoot = realpath((string) $this->config['web_root']);

        return [
            'ok' => $documentRoot !== false && $documentRoot === $webRoot,
            'value' => ['document_root' => $documentRoot, 'web_root' => $webRoot],
        ];
    }

    /** @return array{ok: bool, value: bool} */
    private function writable(string $directory): array
    {
        $probeFile = $directory . '/.dispatch-write-probe';
        $hasDirectory = is_dir($directory) || mkdir($directory, 0750, true) || is_dir($directory);
        $isWritable = $hasDirectory && file_put_contents($probeFile, 'probe') !== false;
        if (is_file($probeFile)) {
            unlink($probeFile);
        }

        return ['ok' => $isWritable, 'value' => $isWritable];
    }
}
