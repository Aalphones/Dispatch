<?php

declare(strict_types=1);

namespace Dispatch\Api;

use Dispatch\Auth\ApiAuth;
use Dispatch\Db\Connection;
use Dispatch\Domain\Actor;
use Dispatch\Domain\DomainError;
use Dispatch\Domain\MediaService;
use Dispatch\Domain\QueueService;
use Dispatch\Http\Request;
use Dispatch\Http\Response;

/** Thema einreichen vom Handy: Formular mit Text und Dateien (ADR-021). */
final class QueueController
{
    private const PRIORITIES = ['now' => 1, 'soon' => 2, 'normal' => 3];
    private const DEFAULT_PRIORITY = 'normal';
    private const FILES_MAX = 10;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /** Die offenen Themen für die Liste im Redaktionsmenü: wichtigste und älteste zuerst. */
    public function list(Request $request): never
    {
        ApiAuth::owner($request);

        try {
            $items = (new QueueService(Connection::open(), $this->config))->list(['open']);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['items' => $items]);
    }

    public function add(Request $request): never
    {
        $actor = ApiAuth::owner($request);

        // Ist die Anfrage größer als post_max_size, verwirft PHP Felder und Dateien und liefert leere Superglobals.
        if ($request->form() === [] && $request->files() === [] && $request->contentLength() > 0) {
            Response::error('too_large', 'Die Anfrage ist zu groß. Einzelne Dateien dürfen höchstens 50 MB haben; weniger Dateien auf einmal senden.', 413);
        }

        try {
            $input = $this->input($request->form());
            $pdo = Connection::open();
            $media = new MediaService($pdo, $this->config);

            $rejected = [];
            $input['media'] = $this->storeFiles($this->uploadedFiles($request->files()), $media, $actor, $this->isOwnMaterial($request->form()), $rejected);
            $item = (new QueueService($pdo, $this->config))->add($input, $actor);
        } catch (DomainError $error) {
            ApiErrors::respond($error);
        }

        Response::json(['item' => $item, 'rejected' => $rejected], 201);
    }

    /**
     * Prüft Text, Priorität und Tiefe, bevor die erste Datei gespeichert wird.
     *
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function input(array $form): array
    {
        $title = $form['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new DomainError('invalid_value', 'title fehlt (Arbeitstitel des Themas).', ['field' => 'title']);
        }

        $priorityName = $form['priority'] ?? self::DEFAULT_PRIORITY;
        if (!is_string($priorityName) || !array_key_exists($priorityName, self::PRIORITIES)) {
            throw new DomainError('invalid_value', 'priority muss normal, soon oder now sein.', ['field' => 'priority']);
        }

        $input = ['title' => $title, 'priority' => self::PRIORITIES[$priorityName]];
        foreach (['brief', 'depth'] as $field) {
            if (isset($form[$field]) && $form[$field] !== '') {
                $input[$field] = $form[$field];
            }
        }
        $input['links'] = $this->links($form['links'] ?? '');

        return $input;
    }

    /** @return list<string> eine Adresse je Zeile, leere Zeilen entfallen */
    private function links(mixed $value): array
    {
        if (!is_string($value)) {
            throw new DomainError('invalid_value', 'links muss Text sein, eine Adresse je Zeile.', ['field' => 'links']);
        }

        $links = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            if (trim($line) !== '') {
                $links[] = trim($line);
            }
        }

        return $links;
    }

    /** @param array<string, mixed> $form */
    private function isOwnMaterial(array $form): bool
    {
        return ($form['own_material'] ?? null) === '1';
    }

    /**
     * Macht aus `$_FILES['files']` (ein oder mehrere Felder) eine flache Liste; leere Dateifelder entfallen.
     *
     * @param array<string, mixed> $files
     * @return list<array{name: string, tmp_name: string, error: int}>
     */
    private function uploadedFiles(array $files): array
    {
        $field = $files['files'] ?? null;
        if (!is_array($field) || !isset($field['name'])) {
            return [];
        }

        $names = (array) $field['name'];
        $uploads = [];
        foreach (array_keys($names) as $index) {
            $error = (int) ((array) $field['error'])[$index];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = [
                'name' => (string) $names[$index],
                'tmp_name' => (string) ((array) $field['tmp_name'])[$index],
                'error' => $error,
            ];
        }

        return $uploads;
    }

    /**
     * Eine abgelehnte Datei bricht nichts ab; sie landet in `$rejected`.
     *
     * @param list<array{name: string, tmp_name: string, error: int}> $uploads
     * @param list<array{name: string, error: string, message: string}> $rejected
     * @return list<array{uid: string, note: null, own_material: bool}>
     */
    private function storeFiles(array $uploads, MediaService $media, Actor $actor, bool $isOwnMaterial, array &$rejected): array
    {
        $attachments = [];
        foreach ($uploads as $index => $upload) {
            $problem = $this->uploadProblem($upload, $index);
            if ($problem === null) {
                $problem = $this->tryStore($upload, $media, $actor, $isOwnMaterial, $attachments);
            }
            if ($problem !== null) {
                $rejected[] = ['name' => $upload['name'], 'error' => $problem[0], 'message' => $problem[1]];
            }
        }

        return $attachments;
    }

    /**
     * @param array{name: string, tmp_name: string, error: int} $upload
     * @return array{0: string, 1: string}|null Fehlercode und Meldung
     */
    private function uploadProblem(array $upload, int $index): ?array
    {
        if ($index >= self::FILES_MAX) {
            return ['too_many_files', sprintf('Höchstens %d Dateien je Thema.', self::FILES_MAX)];
        }
        if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['too_large', 'Die Datei ist zu groß (höchstens 50 MB).'];
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            return ['upload_failed', 'Die Datei kam nicht vollständig an. Bitte noch einmal versuchen.'];
        }

        return null;
    }

    /**
     * @param array{name: string, tmp_name: string, error: int} $upload
     * @param list<array{uid: string, note: null, own_material: bool}> $attachments
     * @return array{0: string, 1: string}|null Fehlercode und Meldung
     */
    private function tryStore(array $upload, MediaService $media, Actor $actor, bool $isOwnMaterial, array &$attachments): ?array
    {
        $bytes = file_get_contents($upload['tmp_name']);
        if ($bytes === false || $bytes === '') {
            return ['upload_failed', 'Die Datei ist leer oder nicht lesbar.'];
        }

        try {
            $medium = $media->store($bytes, [], 'upload', $actor);
        } catch (DomainError $error) {
            return [$error->errorCode, $error->getMessage()];
        }

        $uid = (string) $medium['uid'];
        $isAlreadyAttached = in_array($uid, array_column($attachments, 'uid'), true);
        if (!$isAlreadyAttached) {
            $attachments[] = ['uid' => $uid, 'note' => null, 'own_material' => $isOwnMaterial];
        }

        return null;
    }
}
