<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Http\Request;
use Dispatch\Http\Response;

/**
 * Liefert Feed und Sitemap durch die Brücke aus, damit ihr Abruf die Fälligkeitsprüfung auslöst (ADR-029).
 * Inhalt ist die vom Builder geschriebene Datei.
 */
final class FeedController
{
    private const TYPES = [
        'feed.xml' => 'application/rss+xml; charset=utf-8',
        'sitemap.xml' => 'application/xml; charset=utf-8',
    ];

    public function __construct(private readonly array $config)
    {
    }

    public function serve(Request $request, string $file): never
    {
        $path = rtrim((string) $this->config['web_root'], '/') . '/' . $file;
        if (!is_file($path)) {
            Response::error('not_found', 'Die Datei gibt es noch nicht.', 404);
        }

        clearstatcache(true, $path);
        $modifiedAt = (int) filemtime($path);
        $size = (int) filesize($path);
        // Gleiche Form wie der ETag, den Apache für statische Dateien bildet.
        $etag = '"d-' . dechex($modifiedAt) . '-' . dechex($size) . '"';
        $headers = [
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT',
            'Cache-Control' => 'public, max-age=300',
        ];

        if ($this->isUnchanged($request, $etag, $modifiedAt)) {
            Response::notModified($headers);
        }

        Response::file($path, self::TYPES[$file], $headers, $request->method() !== 'HEAD');
    }

    private function isUnchanged(Request $request, string $etag, int $modifiedAt): bool
    {
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if (str_starts_with($candidate, 'W/')) {
                    $candidate = substr($candidate, 2);
                }
                if ($candidate === $etag || $candidate === '*') {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = $request->header('If-Modified-Since');
        if ($ifModifiedSince === null) {
            return false;
        }
        $since = strtotime($ifModifiedSince);

        return $since !== false && $since >= $modifiedAt;
    }
}
