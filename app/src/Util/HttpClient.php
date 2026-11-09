<?php

declare(strict_types=1);

namespace Dispatch\Util;

use Dispatch\Domain\DomainError;

/**
 * Einziger Weg nach außen (php.md, Critical Rule 4). Jede Anfrage — auch jedes Redirect-Ziel — wird
 * geprüft und auf die geprüfte Adresse festgenagelt, damit ein zweiter DNS-Abruf nichts mehr ändert.
 */
final class HttpClient
{
    private const REDIRECT_STATUS = [301, 302, 303, 307, 308];

    /** @param array<string, mixed> $httpConfig */
    public function __construct(private readonly array $httpConfig, private readonly string $baseUrl = '')
    {
    }

    public function get(string $url, int $maxBytes): HttpResponse
    {
        $currentUrl = $url;
        $maxRedirects = (int) $this->httpConfig['max_redirects'];

        for ($redirects = 0; $redirects <= $maxRedirects; $redirects++) {
            $target = $this->checkUrl($currentUrl);
            $response = $this->request($target, $maxBytes);
            $location = $response['location'];

            if (in_array($response['status'], self::REDIRECT_STATUS, true) && $location !== null) {
                $currentUrl = $this->resolveLocation($currentUrl, $location);
                continue;
            }
            if ($response['status'] !== 200) {
                throw new DomainError('fetch_failed', sprintf('Abruf lieferte Status %d.', $response['status']), ['status' => $response['status']]);
            }

            return new HttpResponse($response['status'], $response['contentType'], $response['body'], $currentUrl);
        }

        throw new DomainError('too_many_redirects', sprintf('Die Adresse leitet öfter als %d Mal weiter. Bitte die endgültige Adresse angeben.', $maxRedirects));
    }

    /**
     * @return array{url: string, host: string, port: int, ip: string}
     */
    private function checkUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw $this->rejected('Die Adresse ist keine gültige http(s)-URL.', $url);
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw $this->rejected('Nur Adressen mit http:// oder https:// sind erlaubt.', $url);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw $this->rejected('Adressen mit Benutzer oder Passwort sind nicht erlaubt.', $url);
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = $parts['port'] ?? $defaultPort;
        // Lokale Entwicklung (allow_private) braucht beliebige Ports, etwa `php -S` auf 9000.
        if (!(bool) $this->httpConfig['allow_private'] && $port !== 80 && $port !== 443) {
            throw $this->rejected('Nur die Ports 80 und 443 sind erlaubt.', $url);
        }

        $host = trim($parts['host'], '[]');
        if ($host === '') {
            throw $this->rejected('Die Adresse enthält keinen Host.', $url);
        }

        $addresses = $this->resolve($host);
        if ($addresses === []) {
            throw $this->rejected('Der Host lässt sich nicht auflösen.', $url);
        }
        foreach ($addresses as $address) {
            if (!$this->isAllowedAddress($address)) {
                throw $this->rejected('Ziel liegt in einem privaten oder reservierten Netz.', $url);
            }
        }

        return ['url' => $url, 'host' => $host, 'port' => $port, 'ip' => $addresses[0]];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        foreach ($records === false ? [] : $records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }
        if ($addresses === []) {
            // Hosts-Datei und Kurzschreibweisen (z. B. localhost, 2130706433): die Adresse wird trotzdem geprüft.
            $addresses = gethostbynamel($host) ?: [];
        }

        return $addresses;
    }

    private function isAllowedAddress(string $address): bool
    {
        if ((bool) $this->httpConfig['allow_private']) {
            return true;
        }
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $address, $mapped) === 1) {
            $address = $mapped[1];
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /**
     * @param array{url: string, host: string, port: int, ip: string} $target
     * @return array{status: int, contentType: string, body: string, location: string|null}
     */
    private function request(array $target, int $maxBytes): array
    {
        $body = '';
        $location = null;
        $isTooLarge = false;

        $curl = curl_init($target['url']);
        $pinnedIp = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        curl_setopt_array($curl, [
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $target['host'], $target['port'], $pinnedIp)],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) $this->httpConfig['connect_timeout'],
            CURLOPT_TIMEOUT => (int) $this->httpConfig['timeout'],
            CURLOPT_USERAGENT => 'DISPATCH/1 (+' . $this->baseUrl . ')',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$isTooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $isTooLarge = true;

                    return -1;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
                if (stripos($line, 'location:') === 0) {
                    $location = trim(substr($line, 9));
                }

                return strlen($line);
            },
        ]);

        $isOk = curl_exec($curl);
        if ($isOk === false) {
            if ($isTooLarge) {
                throw new DomainError('too_large', sprintf('Die Datei ist größer als %d MB.', intdiv($maxBytes, 1024 * 1024)), ['max_bytes' => $maxBytes]);
            }

            throw new DomainError('fetch_failed', 'Abruf fehlgeschlagen: ' . curl_error($curl), ['url' => $target['url']]);
        }

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'contentType' => (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
            'body' => $body,
            'location' => $location,
        ];
    }

    private function resolveLocation(string $currentUrl, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $current = parse_url($currentUrl);
        $scheme = $current['scheme'] ?? 'http';
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        $authority = $scheme . '://' . ($current['host'] ?? '') . (isset($current['port']) ? ':' . $current['port'] : '');
        if (str_starts_with($location, '/')) {
            return $authority . $location;
        }

        $directory = substr($current['path'] ?? '/', 0, (int) strrpos($current['path'] ?? '/', '/') + 1);

        return $authority . $directory . $location;
    }

    private function rejected(string $reason, string $url): DomainError
    {
        return new DomainError('url_rejected', $reason, ['url' => $url]);
    }
}
