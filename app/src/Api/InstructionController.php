<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DocumentService;
use Dispatch\Domain\DomainError;
use Dispatch\Http\Request;
use Dispatch\Http\Response;
use Dispatch\Mcp\McpController;
use Dispatch\Mcp\Prompts;
use Dispatch\Mcp\Tool;
use Dispatch\Util\Markdown;

/**
 * Ansicht „Anweisungen für Agenten“ im Redaktionsmodus: alles, was ein Agent von DISPATCH bekommt,
 * dazu Fassungen der Richtlinien und Präferenzen (ADR-031). Nur der Inhaber.
 */
final class InstructionController
{
    /** Der Router lässt im Pfad keinen Unterstrich zu. */
    private const KEYS_BY_SLUG = ['editorial-guidelines' => 'editorial_guidelines', 'preferences' => 'preferences'];

    /** Reihenfolge verbindlich; je Eintrag Titel und Zeitpunkt, zu dem der Agent den Text bekommt. */
    private const TEXTS = [
        'instructions' => ['Server-Anweisung', 'beim Verbinden, an jeden Agenten'],
        'workflow-scheduled' => ['Workflow: geplanter Artikel', 'im Briefing, wenn weder Auftrag noch zurückgegebener Entwurf warten'],
        'workflow-queue' => ['Workflow: Auftrag aus der Queue', 'im Briefing, wenn ein Thema in der Queue liegt'],
        'workflow-returned' => ['Workflow: zurückgegebener Entwurf', 'im Briefing, wenn du einen Entwurf zurückgegeben hast'],
        'style-guide' => ['Style-Guide', 'über get_style_guide, vor dem ersten Schreiben eines Laufs'],
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function show(Request $request): never
    {
        ApiAuth::owner($request);

        $pdo = Connection::open();
        $services = McpController::services($pdo, $this->config);
        $registry = McpController::registry($services);

        $documents = [];
        foreach (array_keys(self::KEYS_BY_SLUG) as $slug) {
            $documents[] = $this->document($services['documents'], $slug);
        }

        $tools = array_map(
            fn (Tool $tool): array => $tool->describe() + ['scope' => $tool->scope, 'group' => $registry->groupOf($tool->name)],
            $registry->all()
        );

        $prompts = array_map(
            function (array $prompt): array {
                $prompt['html'] = Markdown::toHtml((string) $prompt['text']);
                unset($prompt['text']);

                return $prompt;
            },
            (new Prompts())->catalog()
        );

        Response::json([
            'documents' => $documents,
            'tools' => $tools,
            'prompts' => $prompts,
            'texts' => $this->texts(),
            'form_rules' => (array) $this->config['form_rules'],
        ]);
    }

    public function preview(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $content = $request->json()['content'] ?? null;
            if (!is_string($content)) {
                throw new DomainError('invalid_value', 'Das Feld „content“ muss Text sein.', ['field' => 'content']);
            }
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['html' => Markdown::toHtml($content)]);
    }

    public function save(Request $request, string $slug): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $key = $this->keyFor($slug);
            $body = $request->json();
            $content = $this->requireText($body, 'content');
            $note = $this->optionalText($body, 'note');
            $base = $this->baseRevision($body);

            $service = new DocumentService(Connection::open(), $this->config);
            $service->update($key, $content, null, $note, $actor, $base);
            $document = $this->document($service, $slug);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['document' => $document]);
    }

    public function history(Request $request, string $slug): never
    {
        ApiAuth::owner($request);

        try {
            $revisions = (new DocumentService(Connection::open(), $this->config))->history($this->keyFor($slug));
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['revisions' => $revisions]);
    }

    public function revision(Request $request, string $slug, int $revision): never
    {
        ApiAuth::owner($request);

        try {
            $result = (new DocumentService(Connection::open(), $this->config))->revisionWithDiff($this->keyFor($slug), $revision);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json($result);
    }

    public function restore(Request $request, string $slug): never
    {
        $actor = ApiAuth::owner($request);

        try {
            $key = $this->keyFor($slug);
            $body = $request->json();
            $revision = $body['revision'] ?? null;
            if (!is_int($revision)) {
                throw new DomainError('invalid_value', 'Das Feld „revision“ muss eine Ganzzahl sein.', ['field' => 'revision']);
            }
            $base = $this->baseRevision($body);

            $service = new DocumentService(Connection::open(), $this->config);
            $service->restore($key, $revision, $base, $actor);
            $document = $this->document($service, $slug);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['document' => $document]);
    }

    private function keyFor(string $slug): string
    {
        if (!array_key_exists($slug, self::KEYS_BY_SLUG)) {
            throw new DomainError(
                'not_found',
                sprintf('„%s“ lässt sich hier nicht ansehen oder ändern. Gültig: %s.', $slug, implode(', ', array_keys(self::KEYS_BY_SLUG))),
                ['slug' => $slug]
            );
        }

        return self::KEYS_BY_SLUG[$slug];
    }

    /**
     * Mit `force: true` entfällt die Konfliktprüfung; sonst ist `base_revision` Pflicht.
     *
     * @param array<string, mixed> $body
     */
    private function baseRevision(array $body): ?int
    {
        $force = $body['force'] ?? false;
        if (!is_bool($force)) {
            throw new DomainError('invalid_value', 'Das Feld „force“ muss wahr oder falsch sein.', ['field' => 'force']);
        }

        $base = $body['base_revision'] ?? null;
        if ($force) {
            return null;
        }
        if (!is_int($base)) {
            throw new DomainError('invalid_value', 'Das Feld „base_revision“ muss eine Ganzzahl sein.', ['field' => 'base_revision']);
        }

        return $base;
    }

    /** @param array<string, mixed> $body */
    private function requireText(array $body, string $field): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value)) {
            throw new DomainError('invalid_value', sprintf('Das Feld „%s“ muss Text sein.', $field), ['field' => $field]);
        }

        return $value;
    }

    /** @param array<string, mixed> $body */
    private function optionalText(array $body, string $field): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null) {
            return null;
        }

        return $this->requireText($body, $field);
    }

    /** @return array<string, mixed> */
    private function document(DocumentService $service, string $slug): array
    {
        $key = self::KEYS_BY_SLUG[$slug];
        $document = $service->get($key);
        $latest = $service->history($key)[0] ?? null;

        return [
            'key' => $key,
            'slug' => $slug,
            'title' => $document['title'],
            'revision' => $document['revision'],
            'updated_at' => $document['updated_at'],
            'actor' => $latest['actor'] ?? null,
            'note' => $latest['note'] ?? null,
            'content' => $document['content'],
            'html' => Markdown::toHtml((string) $document['content']),
        ];
    }

    /** @return list<array<string, string>> */
    private function texts(): array
    {
        $texts = [];
        foreach (self::TEXTS as $key => [$title, $when]) {
            $texts[] = [
                'key' => $key,
                'title' => $title,
                'when' => $when,
                'source' => 'app/mcp/' . $key . '.md',
                'html' => Markdown::toHtml(trim((string) file_get_contents(dirname(__DIR__, 2) . '/mcp/' . $key . '.md'))),
            ];
        }

        return $texts;
    }
}
