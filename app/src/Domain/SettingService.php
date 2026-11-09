<?php

declare(strict_types=1);

namespace Dispatch\Domain;

use Dispatch\Db\Transaction;
use PDO;

final class SettingService
{
    public const PUBLIC_KEYS = [
        'site_title', 'site_tagline', 'site_description', 'author_name', 'author_url',
        'accent', 'ai_disclosure', 'ai_disclosure_text',
    ];

    public const INTERNAL_KEYS = ['language', 'locale', 'posts_per_page', 'queue_claim_minutes'];

    public const DEFAULTS = [
        'site_title' => 'DISPATCH',
        'site_tagline' => 'Your agents. Your publication.',
        'site_description' => '',
        'author_name' => '',
        'author_url' => '',
        'accent' => '#b3261e',
        'ai_disclosure' => true,
        'ai_disclosure_text' => 'Dieser Artikel wurde mit KI-Unterstützung recherchiert und geschrieben und vor der Veröffentlichung vom Herausgeber geprüft und freigegeben.',
        'language' => 'de',
        'locale' => 'de_DE',
        'posts_per_page' => 12,
        'queue_claim_minutes' => 180,
    ];

    /** Höchstlängen der Zeichenketten (Zeichen, nach dem Trimmen). */
    private const MAX_LENGTH = [
        'site_title' => 80,
        'site_tagline' => 120,
        'site_description' => 300,
        'author_name' => 120,
        'author_url' => 300,
        'ai_disclosure_text' => 500,
    ];

    private SettingRepository $settings;
    private ChangeService $changes;
    private AuditRepository $audit;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly PDO $pdo, private readonly array $config)
    {
        $this->settings = new SettingRepository($pdo);
        $this->changes = new ChangeService($pdo, $config);
        $this->audit = new AuditRepository($pdo);
    }

    public function get(string $name): mixed
    {
        if (!array_key_exists($name, self::DEFAULTS)) {
            throw new DomainError('unknown_setting', sprintf('Die Einstellung „%s“ gibt es nicht.', $name), ['field' => $name]);
        }

        $stored = $this->settings->get($name);

        return $stored ?? self::DEFAULTS[$name];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace(self::DEFAULTS, $this->settings->all());
    }

    /** @return array<string, mixed> */
    public function formRules(): array
    {
        return (array) $this->config['form_rules'];
    }

    /**
     * Inhaber: alles sofort. Andere: interne Werte sofort, öffentliche als ein Vorschlag.
     *
     * @param array<string, mixed> $values
     * @return array{applied: list<string>, change_id: int|null}
     */
    public function set(array $values, Actor $actor): array
    {
        $normalized = $this->normalizeAll($values);

        return Transaction::join($this->pdo, function () use ($normalized, $actor): array {
            $direct = [];
            $proposed = [];
            foreach ($normalized as $name => $value) {
                if ($actor->isOwner() || in_array($name, self::INTERNAL_KEYS, true)) {
                    $direct[$name] = $value;
                } else {
                    $proposed[$name] = $value;
                }
            }

            $this->write($direct, $actor);
            $changeId = null;
            if ($proposed !== []) {
                $changeId = $this->changes->propose('setting', 'settings', $proposed, $actor);
            }

            return ['applied' => array_keys($direct), 'change_id' => $changeId];
        });
    }

    /**
     * Übernahme eines Vorschlags; läuft in der Transaktion von ChangeService::apply.
     *
     * @param array<string, mixed> $payload
     */
    public function applyChange(string $key, array $payload): void
    {
        $this->write($this->normalizeAll($payload), Actor::owner());
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalizeAll(array $values): array
    {
        $normalized = [];
        foreach ($values as $name => $value) {
            $name = (string) $name;
            $this->assertChangeable($name);
            $normalized[$name] = $this->normalize($name, $value);
        }

        return $normalized;
    }

    private function assertChangeable(string $name): void
    {
        $fixed = array_merge(array_keys($this->formRules()), ['publish_mode', 'stats_enabled']);
        if (in_array($name, $fixed, true)) {
            throw new DomainError('not_changeable', 'Formvorgaben stehen in config.php und ändern sich nur über den Inhaber.', ['field' => $name]);
        }
        if (!array_key_exists($name, self::DEFAULTS)) {
            throw new DomainError('unknown_setting', sprintf('Die Einstellung „%s“ gibt es nicht.', $name), ['field' => $name]);
        }
    }

    /** @param array<string, mixed> $values */
    private function write(array $values, Actor $actor): void
    {
        $audit = $this->audit;
        foreach ($values as $name => $value) {
            $old = $this->get($name);
            $this->settings->upsert($name, $value);
            $audit->record($actor, 'setting.set', 'setting:' . $name, ['old' => $old, 'new' => $value]);
        }
    }

    private function normalize(string $name, mixed $value): mixed
    {
        switch ($name) {
            case 'ai_disclosure':
                if (!is_bool($value)) {
                    throw $this->invalid($name, 'muss true oder false sein');
                }

                return $value;

            case 'posts_per_page':
                return $this->integerInRange($name, $value, 1, 100);

            case 'queue_claim_minutes':
                return $this->integerInRange($name, $value, 15, 1440);
        }

        if (!is_string($value)) {
            throw $this->invalid($name, 'muss ein Text sein');
        }
        $text = trim($value);

        switch ($name) {
            case 'site_title':
                if ($text === '') {
                    throw $this->invalid($name, 'darf nicht leer sein');
                }
                break;

            case 'accent':
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $text) !== 1) {
                    throw $this->invalid($name, 'muss eine Farbe im Format #rrggbb sein, z. B. #b3261e');
                }

                return $text;

            case 'language':
                if (!in_array($text, ['de', 'en'], true)) {
                    throw $this->invalid($name, 'muss „de“ oder „en“ sein');
                }

                return $text;

            case 'locale':
                if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $text) !== 1) {
                    throw $this->invalid($name, 'muss die Form „de_DE“ haben');
                }

                return $text;

            case 'author_url':
                if ($text !== '' && preg_match('#^https?://\S+$#', $text) !== 1) {
                    throw $this->invalid($name, 'muss leer sein oder mit http:// oder https:// beginnen');
                }
                break;
        }

        $maxLength = self::MAX_LENGTH[$name] ?? null;
        if ($maxLength !== null && mb_strlen($text) > $maxLength) {
            throw $this->invalid($name, sprintf('darf höchstens %d Zeichen lang sein', $maxLength));
        }

        return $text;
    }

    private function integerInRange(string $name, mixed $value, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw $this->invalid($name, sprintf('muss eine ganze Zahl von %d bis %d sein', $min, $max));
        }

        return $value;
    }

    private function invalid(string $name, string $reason): DomainError
    {
        return new DomainError('invalid_value', sprintf('Der Wert für „%s“ %s.', $name, $reason), ['field' => $name]);
    }
}
