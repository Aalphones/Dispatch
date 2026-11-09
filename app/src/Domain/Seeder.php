<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

/** Legt fehlende Einstellungen und Dokumente aus app/defaults/ an; Vorhandenes bleibt unberührt. */
final class Seeder
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
    }

    /** @return list<string> angelegte Schlüssel als `setting:<name>` / `document:<key>` */
    public function run(Actor $actor): array
    {
        return Transaction::join($this->pdo, function () use ($actor): array {
            return array_merge($this->seedSettings($actor), $this->seedDocuments($actor));
        });
    }

    /** @return list<string> */
    private function seedSettings(Actor $actor): array
    {
        $settings = new SettingRepository($this->pdo);
        $audit = new AuditRepository($this->pdo);

        $created = [];
        foreach (SettingService::DEFAULTS as $name => $value) {
            if ($settings->exists($name)) {
                continue;
            }
            $settings->upsert($name, $value);
            $audit->record($actor, 'setting.seed', 'setting:' . $name);
            $created[] = 'setting:' . $name;
        }

        return $created;
    }

    /** @return list<string> */
    private function seedDocuments(Actor $actor): array
    {
        $documents = new DocumentRepository($this->pdo);
        $audit = new AuditRepository($this->pdo);

        $created = [];
        $keys = array_merge(DocumentService::PUBLIC_KEYS, DocumentService::INTERNAL_KEYS);
        foreach ($keys as $key) {
            if ($documents->get($key) !== null) {
                continue;
            }

            [$title, $content] = $this->readDefault($key);
            $documents->insert($key, $title, $content, DocumentService::PUBLIC_SLUGS[$key] ?? null);
            $documents->insertRevision($key, 1, $content, 'Startinhalt', $actor->name);
            $audit->record($actor, 'document.seed', 'document:' . $key);
            $created[] = 'document:' . $key;
        }

        return $created;
    }

    /** @return array{0: string, 1: string} Titel und Inhalt */
    private function readDefault(string $key): array
    {
        $path = sprintf('%s/defaults/%s.md', (string) $this->config['app_dir'], $key);
        $text = is_file($path) ? file_get_contents($path) : false;
        if ($text === false) {
            throw new \RuntimeException(sprintf('Startinhalt fehlt oder ist nicht lesbar: defaults/%s.md', $key));
        }

        $text = str_replace("\r\n", "\n", $text);
        $newline = strpos($text, "\n");
        $firstLine = $newline === false ? $text : substr($text, 0, $newline);
        if (!str_starts_with($firstLine, '# ')) {
            throw new \RuntimeException(sprintf('defaults/%s.md muss mit „# Titel“ beginnen.', $key));
        }

        $content = $newline === false ? '' : ltrim(substr($text, $newline + 1), "\n");

        return [trim(substr($firstLine, 2)), $content];
    }
}
