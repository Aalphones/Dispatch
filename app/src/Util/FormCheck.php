<?php

declare(strict_types=1);

namespace Dispatch\Util;

/** Reine Formprüfung eines Artikel-Snapshots — keine Datenbank, keine Uhr. */
final class FormCheck
{
    private const TITLE_WARN_LENGTH = 90;
    private const EXCERPT_MIN_LENGTH = 70;
    private const EXCERPT_MAX_LENGTH = 180;
    private const BRIEFING_FACTOR = 0.4;
    private const SUBHEADING_WORD_THRESHOLD = 700;
    private const LONGFORM_MIN_WORDS = 1500;
    private const MAX_CATEGORIES = 3;
    private const DUPLICATE_PERCENT = 80.0;
    private const WORDS_PER_MINUTE = 200;
    private const KINDS_WITHOUT_URL = ['book', 'interview', 'other'];
    private const REFERENCE_PATTERN = '/data-(media|cite)="([^"]*)"/';
    private const SECTION_START_PATTERN = '/(?=<h2\b)/i';
    private const SUMMARY_BOX_PATTERN = '/<(?:aside|div)\b[^>]*\bclass="[^"]*\btldr\b/i';
    private const HEADING_PATTERN = '/<h2\b[^>]*>(.*?)<\/h2>/is';

    /** @var list<array{code: string, field: string, message: string}> */
    private array $errors = [];
    /** @var list<array{code: string, field: string, message: string}> */
    private array $warnings = [];

    /**
     * Die UIDs aller Medien, die der Artikel nennt: Inhalt (auch Galerie-Listen) plus Titelbild.
     *
     * @param array<string, mixed> $snapshot
     * @return list<string>
     */
    public static function mediaUids(array $snapshot): array
    {
        $uids = self::referencedValues((string) $snapshot['content_html'], 'media');
        if (is_string($snapshot['cover_media_uid'] ?? null)) {
            $uids[] = $snapshot['cover_media_uid'];
        }

        return array_values(array_unique($uids));
    }

    /**
     * @param array<string, mixed> $snapshot Arbeitsstand wie `PostService::snapshot()`
     * @param array<string, array<string, mixed>> $mediaByUid Medien der verwendeten UIDs
     * @param list<string> $otherLiveTitles Titel aller anderen Live-Artikel
     * @param array<string, mixed> $rules Formvorgaben aus der Konfiguration
     */
    public function check(array $snapshot, array $mediaByUid, array $otherLiveTitles, array $rules): FormCheckResult
    {
        $this->errors = [];
        $this->warnings = [];

        $html = (string) $snapshot['content_html'];
        $words = Text::wordCount($html);
        $sources = array_values((array) $snapshot['sources']);
        $citations = self::referencedValues($html, 'cite');
        $minimums = $this->minimums($snapshot, $rules);

        $this->checkTitle((string) $snapshot['title']);
        $this->checkExcerpt((string) ($snapshot['excerpt'] ?? ''));
        $this->checkLength($snapshot, $html, $words, $minimums['min_words']);
        $this->checkSummaryBoxes($html);
        $this->checkSourceCount($sources, $minimums['min_sources']);
        $this->checkSourceDetails($sources);
        $this->checkCitations($sources, $citations);
        $this->checkMedia($snapshot, $html, $mediaByUid, $rules);
        $this->checkCategories((array) $snapshot['categories']);
        $this->checkDuplicate((string) $snapshot['title'], $otherLiveTitles);

        $hosts = $this->sourceHosts($sources);
        $metrics = [
            'words' => $words,
            'reading_minutes' => max(1, (int) ceil($words / self::WORDS_PER_MINUTE)),
            'sources' => count($sources),
            'cited_sources' => count(array_filter($sources, static fn (array $source): bool => in_array($source['ref'], $citations, true))),
            'citations' => count($citations),
            'source_domains' => count(array_unique($hosts)),
            'images' => $this->countImages(self::mediaUids($snapshot), $mediaByUid),
            'minimums' => $minimums,
        ];

        return new FormCheckResult($this->errors, $this->warnings, $metrics);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $rules
     * @return array{min_words: int, min_sources: int}
     */
    private function minimums(array $snapshot, array $rules): array
    {
        $isDeep = $snapshot['depth'] === 'deep';
        $minWords = (int) $rules[$isDeep ? 'min_words_deep' : 'min_words'];
        if ($snapshot['layout'] === 'briefing') {
            $minWords = (int) ceil($minWords * self::BRIEFING_FACTOR);
        }

        return ['min_words' => $minWords, 'min_sources' => (int) $rules[$isDeep ? 'min_sources_deep' : 'min_sources']];
    }

    private function checkTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            $this->error('title_missing', 'title', 'Der Titel fehlt. Gib dem Artikel einen Titel.');
        } elseif (mb_strlen($title) > self::TITLE_WARN_LENGTH) {
            $this->warning('title_long', 'title', sprintf('Der Titel hat %d Zeichen. Kürze ihn auf höchstens %d, sonst bricht er in der Vorschau um.', mb_strlen($title), self::TITLE_WARN_LENGTH));
        }
    }

    private function checkExcerpt(string $excerpt): void
    {
        $length = mb_strlen(trim($excerpt));
        if ($length === 0) {
            $this->error('excerpt_missing', 'excerpt', 'Der Teaser fehlt. Schreib 70 bis 180 Zeichen, die zum Lesen einladen.');
        } elseif ($length < self::EXCERPT_MIN_LENGTH || $length > self::EXCERPT_MAX_LENGTH) {
            $this->warning('excerpt_length', 'excerpt', sprintf('Der Teaser hat %d Zeichen. Er soll zwischen %d und %d liegen.', $length, self::EXCERPT_MIN_LENGTH, self::EXCERPT_MAX_LENGTH));
        }
    }

    /** @param array<string, mixed> $snapshot */
    private function checkLength(array $snapshot, string $html, int $words, int $minWords): void
    {
        if ($words < $minWords) {
            $this->error('too_short', 'content_html', sprintf('Der Artikel hat %d Wörter, verlangt sind mindestens %d. Ergänze Inhalt oder wähle das Layout „briefing“.', $words, $minWords));
        }
        if ($words > self::SUBHEADING_WORD_THRESHOLD && preg_match('/<h2\b/i', $html) !== 1) {
            $this->warning('no_subheadings', 'content_html', 'Der Text ist lang und hat keine Zwischenüberschrift. Gliedere ihn mit <h2>.');
        }
        if ($snapshot['layout'] === 'longform' && $words < self::LONGFORM_MIN_WORDS) {
            $this->warning('longform_short', 'layout', sprintf('Das Layout „longform“ ist für mindestens %d Wörter gedacht, der Text hat %d. Nimm „standard“ oder „feature“.', self::LONGFORM_MIN_WORDS, $words));
        }
    }

    /**
     * Pflicht aus den Redaktionsrichtlinien: ein Kasten „Kurz gesagt“ vor der ersten Zwischenüberschrift
     * (Gesamt-Kasten) und einer in jedem h2-Abschnitt. Nur Warnung, damit kurze Meldungen einreichbar bleiben.
     */
    private function checkSummaryBoxes(string $html): void
    {
        if (trim($html) === '') {
            return;
        }

        $missing = [];
        foreach (preg_split(self::SECTION_START_PATTERN, $html) ?: [] as $index => $section) {
            if (preg_match(self::SUMMARY_BOX_PATTERN, $section) === 1) {
                continue;
            }
            $missing[] = $index === 0 ? 'Gesamt-Kasten vor der ersten Zwischenüberschrift' : '„' . self::headingText($section) . '“';
        }

        if ($missing !== []) {
            $this->warning('tldr_missing', 'content_html', sprintf('Es fehlen Kästen „Kurz gesagt“: %s. Setze direkt nach dem Einstieg und am Ende jedes h2-Abschnitts ein <aside class="tldr">.', implode(', ', $missing)));
        }
    }

    private static function headingText(string $section): string
    {
        if (preg_match(self::HEADING_PATTERN, $section, $match) !== 1) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @param list<array<string, mixed>> $sources */
    private function checkSourceCount(array $sources, int $minSources): void
    {
        if (count($sources) < $minSources) {
            $this->error('too_few_sources', 'sources', sprintf('Es gibt %d Quellen, verlangt sind mindestens %d. Ergänze weitere unabhängige Quellen.', count($sources), $minSources));
        }

        $hosts = $this->sourceHosts($sources);
        if (count($hosts) >= 2 && count(array_unique($hosts)) === 1) {
            $this->error('single_domain', 'sources', sprintf('Alle Quellen mit Adresse liegen auf %s. Belege den Artikel auch mit einer unabhängigen Quelle.', $hosts[0]));
        }
    }

    /** @param list<array<string, mixed>> $sources */
    private function checkSourceDetails(array $sources): void
    {
        foreach ($sources as $source) {
            $field = 'sources.' . $source['ref'];
            $needsUrl = !in_array($source['kind'], self::KINDS_WITHOUT_URL, true);
            if ($needsUrl && trim((string) ($source['url'] ?? '')) === '') {
                $this->error('source_url_missing', $field, sprintf('Die Quelle „%s“ braucht eine Adresse (url), weil sie vom Typ „%s“ ist.', $source['ref'], $source['kind']));
            }
            if (trim((string) ($source['publisher'] ?? '')) === '') {
                $this->warning('source_publisher_missing', $field, sprintf('Die Quelle „%s“ nennt keinen Herausgeber (publisher). Trag ihn nach.', $source['ref']));
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param list<string> $citations
     */
    private function checkCitations(array $sources, array $citations): void
    {
        if ($citations === []) {
            $this->error('no_citations', 'content_html', 'Der Text zitiert keine Quelle. Setze an belegte Aussagen ein data-cite mit dem ref der Quelle.');
        }

        $refs = array_map(static fn (array $source): string => (string) $source['ref'], $sources);
        foreach (array_unique($citations) as $citation) {
            if (!in_array($citation, $refs, true)) {
                $this->error('unknown_citation', 'content_html', sprintf('data-cite verweist auf „%s“, aber es gibt keine Quelle mit diesem ref. Lege die Quelle an oder korrigiere den Verweis.', $citation));
            }
        }
        foreach ($refs as $ref) {
            if (!in_array($ref, $citations, true)) {
                $this->warning('uncited_source', 'sources.' . $ref, sprintf('Die Quelle „%s“ wird im Text nirgends zitiert. Zitiere sie oder entferne sie.', $ref));
            }
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, array<string, mixed>> $mediaByUid
     * @param array<string, mixed> $rules
     */
    private function checkMedia(array $snapshot, string $html, array $mediaByUid, array $rules): void
    {
        if (($snapshot['cover_media_uid'] ?? null) === null && $rules['require_cover']) {
            $this->error('cover_missing', 'cover_media_uid', 'Der Artikel hat kein Titelbild. Setze cover_media_uid auf ein importiertes Bild.');
        }

        foreach (self::mediaUids($snapshot) as $uid) {
            if (!isset($mediaByUid[$uid])) {
                $this->error('unknown_media', 'media.' . $uid, sprintf('Das Medium „%s“ gibt es nicht. Importiere es zuerst oder korrigiere die UID.', $uid));
                continue;
            }
            $this->checkMedium($uid, $mediaByUid[$uid], (bool) $rules['require_image_license']);
        }
    }

    /** @param array<string, mixed> $medium */
    private function checkMedium(string $uid, array $medium, bool $requireLicense): void
    {
        $required = [];
        if (in_array($medium['kind'], ['image', 'video'], true)) {
            $required[] = 'alt';
        }
        if ($requireLicense) {
            array_push($required, 'credit', 'license');
        }
        if ($medium['origin'] !== 'owner') {
            $required[] = 'source_url';
        }

        foreach ($required as $column) {
            if (trim((string) ($medium[$column] ?? '')) === '') {
                $this->error('media_incomplete', 'media.' . $uid . '.' . $column, sprintf('Beim Medium „%s“ fehlt die Angabe „%s“. Ergänze sie in den Medien-Metadaten.', $uid, $column));
            }
        }
    }

    /** @param list<array<string, mixed>> $categories */
    private function checkCategories(array $categories): void
    {
        $count = count($categories);
        if ($count === 0 || $count > self::MAX_CATEGORIES) {
            $this->error('category_count', 'categories', sprintf('Der Artikel hat %d Kategorien, erlaubt sind 1 bis %d.', $count, self::MAX_CATEGORIES));
        }
        if ($count === 0) {
            return;
        }

        $primaryCount = count(array_filter($categories, static fn (array $category): bool => $category['primary'] === true));
        if ($primaryCount !== 1) {
            $this->error('category_primary', 'categories', 'Genau eine Kategorie muss die Hauptkategorie sein (primary: true).');
        }
    }

    /** @param list<string> $otherLiveTitles */
    private function checkDuplicate(string $title, array $otherLiveTitles): void
    {
        $own = mb_strtolower(trim($title));
        if ($own === '') {
            return;
        }

        foreach ($otherLiveTitles as $otherTitle) {
            similar_text($own, mb_strtolower(trim($otherTitle)), $percent);
            if ($percent > self::DUPLICATE_PERCENT) {
                $this->warning('possible_duplicate', 'title', sprintf('Der Titel ähnelt dem veröffentlichten Artikel „%s“ (%d %%). Prüfe, ob das Thema schon behandelt ist.', $otherTitle, (int) round($percent)));
            }
        }
    }

    /**
     * Hosts aller Quellen mit Adresse: klein, ohne führendes „www.“.
     *
     * @param list<array<string, mixed>> $sources
     * @return list<string>
     */
    private function sourceHosts(array $sources): array
    {
        $hosts = [];
        foreach ($sources as $source) {
            $host = parse_url(trim((string) ($source['url'] ?? '')), PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                continue;
            }
            $hosts[] = preg_replace('/^www\./', '', mb_strtolower($host));
        }

        return $hosts;
    }

    /**
     * @param list<string> $uids
     * @param array<string, array<string, mixed>> $mediaByUid
     */
    private function countImages(array $uids, array $mediaByUid): int
    {
        $count = 0;
        foreach ($uids as $uid) {
            if (($mediaByUid[$uid]['kind'] ?? null) === 'image') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Werte aller `data-media` bzw. `data-cite`, Listen an „,“ geteilt. Das HTML stammt aus dem Sanitizer, die Attributform ist fest.
     *
     * @return list<string>
     */
    private static function referencedValues(string $html, string $attribute): array
    {
        preg_match_all(self::REFERENCE_PATTERN, $html, $matches, PREG_SET_ORDER);

        $values = [];
        foreach ($matches as $match) {
            if ($match[1] !== $attribute) {
                continue;
            }
            foreach (explode(',', html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $part) {
                $value = trim($part);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function error(string $code, string $field, string $message): void
    {
        $this->errors[] = ['code' => $code, 'field' => $field, 'message' => $message];
    }

    private function warning(string $code, string $field, string $message): void
    {
        $this->warnings[] = ['code' => $code, 'field' => $field, 'message' => $message];
    }
}
