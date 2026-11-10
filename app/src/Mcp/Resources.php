<?php

declare(strict_types=1);

namespace Dispatch\Mcp;

use Dispatch\Domain\DocumentService;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\PostService;

/** `dispatch://`-Resources: die fünf Dokumente, der Style-Guide und Artikel als Vorlage. */
final class Resources
{
    private const DOCUMENT_PREFIX = 'dispatch://documents/';
    private const POST_PREFIX = 'dispatch://posts/';
    private const STYLE_GUIDE_URI = 'dispatch://style-guide';
    private const STYLE_GUIDE_FILE = __DIR__ . '/../../mcp/style-guide.md';
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(private readonly DocumentService $documents, private readonly PostService $posts)
    {
    }

    /** @return list<array<string, string>> */
    public function list(): array
    {
        $resources = [];
        foreach ($this->documents->list() as $document) {
            $key = (string) $document['doc_key'];
            $resources[] = [
                'uri' => self::DOCUMENT_PREFIX . $key,
                'name' => 'document-' . $key,
                'title' => (string) $document['title'],
                'description' => sprintf('Dokument „%s“ als Markdown', $key),
                'mimeType' => 'text/markdown',
            ];
        }
        $resources[] = [
            'uri' => self::STYLE_GUIDE_URI,
            'name' => 'style-guide',
            'title' => 'Style-Guide',
            'description' => 'Erlaubte HTML-Elemente, Belegsyntax und Pflichtangaben für Artikel',
            'mimeType' => 'text/markdown',
        ];

        return $resources;
    }

    /** @return list<array<string, string>> */
    public function templates(): array
    {
        return [[
            'uriTemplate' => self::POST_PREFIX . '{id}',
            'name' => 'post',
            'title' => 'Artikel',
            'description' => 'Ein Artikel als JSON, mit HTML-Inhalt; {id} ist die Artikel-ID',
            'mimeType' => 'application/json',
        ]];
    }

    /** @return list<array{uri: string, mimeType: string, text: string}> */
    public function read(string $uri): array
    {
        if ($uri === self::STYLE_GUIDE_URI) {
            return [['uri' => $uri, 'mimeType' => 'text/markdown', 'text' => trim((string) file_get_contents(self::STYLE_GUIDE_FILE))]];
        }
        if (str_starts_with($uri, self::DOCUMENT_PREFIX)) {
            return [$this->document($uri, substr($uri, strlen(self::DOCUMENT_PREFIX)))];
        }
        if (str_starts_with($uri, self::POST_PREFIX)) {
            return [$this->post($uri, substr($uri, strlen(self::POST_PREFIX)))];
        }

        throw self::unknown($uri);
    }

    /** @return array{uri: string, mimeType: string, text: string} */
    private function document(string $uri, string $key): array
    {
        try {
            $document = $this->documents->get($key);
        } catch (DomainError) {
            throw self::unknown($uri);
        }

        return ['uri' => $uri, 'mimeType' => 'text/markdown', 'text' => (string) $document['content']];
    }

    /** @return array{uri: string, mimeType: string, text: string} */
    private function post(string $uri, string $id): array
    {
        if (preg_match('/^[1-9][0-9]{0,9}$/', $id) !== 1) {
            throw self::unknown($uri);
        }
        try {
            $post = $this->posts->get((int) $id);
        } catch (DomainError) {
            throw self::unknown($uri);
        }

        return ['uri' => $uri, 'mimeType' => 'application/json', 'text' => json_encode($post, self::JSON_FLAGS)];
    }

    // -32602 statt -32002: ab Protokoll 2026-07-28 ist -32002 für Resources verboten.
    private static function unknown(string $uri): ProtocolError
    {
        return new ProtocolError(
            ProtocolError::INVALID_PARAMS,
            sprintf('Resource „%s“ ist unbekannt. resources/list und resources/templates/list nennen die gültigen Adressen.', $uri),
            400,
            ['uri' => $uri]
        );
    }
}
