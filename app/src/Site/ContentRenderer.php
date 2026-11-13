<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Util\Log;
use Dispatch\Util\Text;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Macht aus dem gespeicherten, bereinigten Artikel-HTML die ausgelieferte Fassung: Medien, Belege,
 * Überschriften-IDs, Kurz-gesagt-Titel.
 *
 * Neue Elemente entstehen nur über die DOM-API (createElement, setAttribute, createTextNode); kein Wert
 * wird per Textverkettung in Markup gesetzt. Der Eingang ist schon durch den Sanitizer gelaufen (ADR-010).
 *
 * Hält den Zustand eines Laufs in Eigenschaften und ist deshalb nicht verschachtelt aufrufbar.
 */
final class ContentRenderer
{
    private const SIZES_BY_LAYOUT = [
        'inline' => '(max-width: 46rem) 100vw, 38rem',
        'left' => '(max-width: 46rem) 100vw, 18rem',
        'right' => '(max-width: 46rem) 100vw, 18rem',
        'wide' => '(max-width: 62rem) 100vw, 53rem',
        'full' => '100vw',
    ];
    private const DEFAULT_LAYOUT = 'inline';
    private const GALLERY_SIZES = '(max-width: 46rem) 50vw, 26rem';
    private const GALLERY_FIRST_SIZES = '(max-width: 46rem) 100vw, 34rem';

    private const GALLERY_VARIANTS = ['mosaic' => 'mosaik', 'carousel' => 'karussell', 'strips' => 'streifen'];
    private const DEFAULT_COMPARISON_LABELS = ['Vorher', 'Nachher'];
    private const DEFAULT_RATING_MAX = '10';
    private const RATING_MAX_VALUES = ['5', '10', '100'];

    private const EMBED_LABELS =['youtube' => 'YouTube', 'vimeo' => 'Vimeo'];
    private const KIND_LABELS = ['image' => 'Foto', 'video' => 'Video', 'audio' => 'Audio'];
    private const DOCUMENT_FALLBACK_TEXT = 'Dokument (PDF)';

    private HTMLDocument $document;

    /** @var array<string, array<string, mixed>> */
    private array $media = [];

    private string $postSlug = '';

    /** Wird gesetzt, sobald ein Baustein mit eigenem Stil entsteht; der Builder lädt dann bausteine.css. */
    private bool $hasComponents = false;

    private int $accordionCount = 0;

    /**
     * @param array<string, mixed> $post PostView
     * @return array{html: string, toc: list<array{id: string, text: string}>, headings: list<string>, text: string, bausteine: bool}
     */
    public function render(array $post, SiteData $data): array
    {
        $this->media = $data->media;
        $this->postSlug = (string) $post['slug'];
        $this->hasComponents = false;
        $this->accordionCount = 0;
        $html = (string) $post['content_html'];

        $this->document = HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR, 'UTF-8');
        $body = $this->document->body;
        if ($body === null) {
            return ['html' => '', 'toc' => [], 'headings' => [], 'text' => '', 'bausteine' => false];
        }

        $this->renderGalleries();
        // Vorher/Nachher und Erklärpunkte tragen ebenfalls data-media und müssen vor renderFigures() ersetzt sein.
        $this->renderComparisons();
        $this->renderHotspots();
        $this->renderFigures();
        $this->renderEmbeds();
        $this->renderRatings();
        $this->renderTimelines();
        $this->renderAccordions();
        $this->renderCitations((array) $post['sources']);
        $toc = $this->addHeadingIds();
        $this->addShortTitles();

        return [
            'html' => $body->innerHTML,
            'toc' => $toc,
            'headings' => $this->headingTexts(),
            'text' => Text::plain($html),
            'bausteine' => $this->hasComponents,
        ];
    }

    private function renderFigures(): void
    {
        foreach ($this->select('figure[data-media]') as $figure) {
            $uid = (string) $figure->getAttribute('data-media');
            $medium = $this->media[$uid] ?? null;
            if ($medium === null) {
                $this->dropMissing($figure, $uid);

                continue;
            }

            $layout = (string) $figure->getAttribute('data-layout');
            if (!isset(self::SIZES_BY_LAYOUT[$layout])) {
                $layout = self::DEFAULT_LAYOUT;
            }
            $existingCaption = $this->firstChildNamed($figure, 'figcaption');

            $figure->replaceWith($this->buildMediumNode($medium, $layout, $existingCaption));
        }
    }

    /** @param array<string, mixed> $medium */
    private function buildMediumNode(array $medium, string $layout, ?Element $existingCaption): Element
    {
        $kind = (string) $medium['kind'];
        if ($kind === 'document') {
            return $this->buildDocumentLink($medium);
        }

        $figure = $this->document->createElement('figure');
        $figure->setAttribute('class', 'abb');
        $figure->setAttribute('data-layout', $layout);

        if ($kind === 'image') {
            $photo = $this->document->createElement('div');
            $photo->setAttribute('class', 'foto');
            $photo->appendChild($this->buildPicture($medium, self::SIZES_BY_LAYOUT[$layout], false));
            $figure->appendChild($photo);
        } else {
            $player = $this->document->createElement($kind === 'video' ? 'video' : 'audio');
            $player->setAttribute('controls', '');
            $player->setAttribute('preload', 'none');
            $player->setAttribute('src', $medium['base_url'] . 'original.' . $medium['ext']);
            $figure->appendChild($player);
        }

        $caption = $this->buildCaption($existingCaption, $medium);
        if ($caption !== null) {
            $figure->appendChild($caption);
        }

        return $figure;
    }

    /** @param array<string, mixed> $medium */
    private function buildDocumentLink(array $medium): Element
    {
        $paragraph = $this->document->createElement('p');
        $paragraph->setAttribute('class', 'dokument');

        $link = $this->document->createElement('a');
        $link->setAttribute('href', $medium['base_url'] . 'original.' . $medium['ext']);
        $caption = trim((string) $medium['caption']);
        $link->textContent = $caption === '' ? self::DOCUMENT_FALLBACK_TEXT : $caption;
        $paragraph->appendChild($link);

        return $paragraph;
    }

    private function renderGalleries(): void
    {
        foreach ($this->select('div.gallery[data-media]') as $gallery) {
            $images = [];
            foreach (explode(',', (string) $gallery->getAttribute('data-media')) as $uid) {
                $medium = $this->media[$uid] ?? null;
                if ($medium === null || $medium['kind'] !== 'image') {
                    Log::error('Galerie-Medium fehlt oder ist kein Bild', ['post' => $this->postSlug, 'uid' => $uid]);

                    continue;
                }
                $images[] = $medium;
            }

            if ($images === []) {
                $gallery->remove();

                continue;
            }
            $variant = self::GALLERY_VARIANTS[(string) $gallery->getAttribute('data-variant')] ?? null;
            if ($variant !== null) {
                $this->hasComponents = true;
            }
            $gallery->replaceWith($this->buildGallery($images, $variant));
        }
    }

    /**
     * @param list<array<string, mixed>> $images
     * @param string|null $variant Ausgabe-Name der Form; ohne bleibt es das bisherige Raster
     */
    private function buildGallery(array $images, ?string $variant): Element
    {
        $figure = $this->document->createElement('figure');
        $figure->setAttribute('class', 'galerie');
        $figure->setAttribute('data-anzahl', (string) count($images));
        if ($variant !== null) {
            $figure->setAttribute('data-variante', $variant);
        }

        // Im Karussell liegen die Fotos in einer Spur, die der Stil quer scrollen lässt.
        $photoParent = $figure;
        if ($variant === 'karussell') {
            $photoParent = $this->document->createElement('div');
            $photoParent->setAttribute('class', 'spur');
            $figure->appendChild($photoParent);
        }

        $credits = [];
        foreach ($images as $index => $medium) {
            $photo = $this->document->createElement('div');
            $photo->setAttribute('class', 'foto');
            $photoCaption = $this->photoCaption($medium);
            if ($photoCaption !== '') {
                $photo->setAttribute('data-unterschrift', $photoCaption);
            }
            $sizes = $index === 0 ? self::GALLERY_FIRST_SIZES : self::GALLERY_SIZES;
            $photo->appendChild($this->buildPicture($medium, $sizes, false));
            $photoParent->appendChild($photo);

            $credit = $this->buildCredit($medium, 'Foto ' . ($index + 1));
            if ($credit !== null) {
                $credits[] = $credit;
            }
        }

        if ($credits !== []) {
            $caption = $this->document->createElement('figcaption');
            foreach ($credits as $position => $credit) {
                if ($position > 0) {
                    $caption->appendChild($this->document->createTextNode(' '));
                }
                $caption->appendChild($credit);
            }
            $figure->appendChild($caption);
        }

        return $figure;
    }

    /**
     * Unterschrift der Bildansicht: Bildunterschrift, „Foto: Urheber“ und Lizenz, getrennt durch „ · “.
     *
     * @param array<string, mixed> $medium
     */
    private function photoCaption(array $medium): string
    {
        $parts = [];
        $caption = trim((string) $medium['caption']);
        if ($caption !== '') {
            $parts[] = $caption;
        }
        $credit = trim((string) $medium['credit']);
        if ($credit !== '') {
            $parts[] = 'Foto: ' . $credit;
        }
        $license = trim((string) $medium['license']);
        if ($license !== '') {
            $parts[] = $license;
        }

        return implode(' · ', $parts);
    }

    private function renderComparisons(): void
    {
        foreach ($this->select('figure.before-after[data-media]') as $figure) {
            $images = [];
            foreach (explode(',', (string) $figure->getAttribute('data-media')) as $uid) {
                $medium = $this->media[trim($uid)] ?? null;
                if ($medium === null || $medium['kind'] !== 'image') {
                    Log::error('Vorher/Nachher-Medium fehlt oder ist kein Bild', ['post' => $this->postSlug, 'uid' => $uid]);

                    continue;
                }
                $images[] = $medium;
            }

            if (count($images) !== 2) {
                $figure->remove();

                continue;
            }

            $this->hasComponents = true;
            $figure->replaceWith($this->buildComparison($images, $this->comparisonLabels($figure), $this->firstChildNamed($figure, 'figcaption')));
        }
    }

    /** @return array{0: string, 1: string} */
    private function comparisonLabels(Element $figure): array
    {
        $labels = self::DEFAULT_COMPARISON_LABELS;
        $parts = explode('|', (string) $figure->getAttribute('data-labels'));
        foreach ([0, 1] as $position) {
            $label = trim($parts[$position] ?? '');
            if ($label !== '') {
                $labels[$position] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param list<array<string, mixed>> $images genau zwei: alt, neu
     * @param array{0: string, 1: string} $labels
     */
    private function buildComparison(array $images, array $labels, ?Element $existingCaption): Element
    {
        $figure = $this->document->createElement('figure');
        $figure->setAttribute('class', 'vergleich');

        $surface = $this->document->createElement('div');
        $surface->setAttribute('class', 'vergleich-flaeche');
        foreach ([0 => 'vorher', 1 => 'nachher'] as $position => $side) {
            $half = $this->document->createElement('div');
            $half->setAttribute('class', 'vergleich-bild ' . $side);
            $half->appendChild($this->buildPicture($images[$position], self::SIZES_BY_LAYOUT['inline'], false));
            $mark = $this->document->createElement('span');
            $mark->setAttribute('class', 'vergleich-marke');
            $mark->textContent = $labels[$position];
            $half->appendChild($mark);
            $surface->appendChild($half);
        }
        $figure->appendChild($surface);

        $caption = $this->document->createElement('figcaption');
        if ($existingCaption !== null) {
            while ($existingCaption->firstChild !== null) {
                $caption->appendChild($existingCaption->firstChild);
            }
        }
        foreach ($images as $position => $medium) {
            $credit = $this->buildCredit($medium, 'Foto ' . ($position + 1));
            if ($credit === null) {
                continue;
            }
            if ($caption->firstChild !== null) {
                $caption->appendChild($this->document->createTextNode(' '));
            }
            $caption->appendChild($credit);
        }
        if ($caption->firstChild !== null) {
            $figure->appendChild($caption);
        }

        return $figure;
    }

    private function renderHotspots(): void
    {
        foreach ($this->select('figure.hotspots[data-media]') as $figure) {
            $uid = (string) $figure->getAttribute('data-media');
            $medium = $this->media[$uid] ?? null;
            if ($medium === null || $medium['kind'] !== 'image') {
                Log::error('Erklärbild-Medium fehlt oder ist kein Bild', ['post' => $this->postSlug, 'uid' => $uid]);
                $figure->remove();

                continue;
            }

            $this->hasComponents = true;
            $figure->replaceWith($this->buildHotspots($medium, $this->firstChildNamed($figure, 'ol'), $this->firstChildNamed($figure, 'figcaption')));
        }
    }

    /** @param array<string, mixed> $medium */
    private function buildHotspots(array $medium, ?Element $list, ?Element $existingCaption): Element
    {
        $figure = $this->document->createElement('figure');
        $figure->setAttribute('class', 'erklaerbild');

        $surface = $this->document->createElement('div');
        $surface->setAttribute('class', 'erklaerbild-flaeche');
        $surface->appendChild($this->buildPicture($medium, self::SIZES_BY_LAYOUT['inline'], false));
        $figure->appendChild($surface);

        if ($list !== null) {
            $list->setAttribute('class', 'erklaerbild-liste');
            $figure->appendChild($list);
        }

        $caption = $this->buildCaption($existingCaption, $medium);
        if ($caption !== null) {
            $figure->appendChild($caption);
        }

        return $figure;
    }

    private function renderRatings(): void
    {
        foreach ($this->select('ul.rating') as $rating) {
            $maxAttribute = (string) $rating->getAttribute('data-max');
            $max = self::DEFAULT_RATING_MAX;
            if (in_array($maxAttribute, self::RATING_MAX_VALUES, true)) {
                $max = $maxAttribute;
            }

            $rows = $this->document->createElement('ul');
            foreach ($rating->children as $item) {
                $score = $this->normalizeScore((string) $item->getAttribute('data-score'));
                if ($item->localName !== 'li' || $score === null) {
                    continue;
                }
                $rows->appendChild($this->buildRatingRow($this->normalizedText($item), $score, $max));
            }

            if ($rows->firstChild === null) {
                $rating->remove();

                continue;
            }

            $this->hasComponents = true;
            $block = $this->document->createElement('div');
            $block->setAttribute('class', 'wertung');
            $total = $this->normalizeScore((string) $rating->getAttribute('data-total'));
            if ($total !== null) {
                $block->appendChild($this->buildRatingTotal($total, $max));
            }
            $block->appendChild($rows);
            $rating->replaceWith($block);
        }
    }

    private function buildRatingTotal(string $total, string $max): Element
    {
        $paragraph = $this->document->createElement('p');
        $paragraph->setAttribute('class', 'wertung-gesamt');

        $label = $this->document->createElement('span');
        $label->setAttribute('class', 'klein');
        $label->textContent = 'Gesamt';
        $paragraph->appendChild($label);

        $value = $this->document->createElement('b');
        $value->textContent = $this->formatScore($total);
        $paragraph->appendChild($value);

        $unit = $this->document->createElement('span');
        $unit->textContent = 'von ' . $max;
        $paragraph->appendChild($unit);

        return $paragraph;
    }

    private function buildRatingRow(string $name, string $score, string $max): Element
    {
        $row = $this->document->createElement('li');

        $nameSpan = $this->document->createElement('span');
        $nameSpan->setAttribute('class', 'wertung-name');
        $nameSpan->textContent = $name;
        $row->appendChild($nameSpan);

        $meter = $this->document->createElement('meter');
        $meter->setAttribute('min', '0');
        $meter->setAttribute('max', $max);
        $meter->setAttribute('value', $score);
        $meter->textContent = $this->formatScore($score) . ' von ' . $max;
        $row->appendChild($meter);

        $valueSpan = $this->document->createElement('span');
        $valueSpan->setAttribute('class', 'wertung-wert');
        $valueSpan->textContent = $this->formatScore($score);
        $row->appendChild($valueSpan);

        return $row;
    }

    /** @return string|null Zahl mit Punkt und ohne „.0“, null bei ungültigem Wert (0 bis 999, eine Nachkommastelle) */
    private function normalizeScore(string $raw): ?string
    {
        $value = str_replace(',', '.', trim($raw));
        if (preg_match('/^\d{1,3}(\.\d)?$/', $value) !== 1) {
            return null;
        }

        return preg_replace('/\.0$/', '', $value);
    }

    private function formatScore(string $value): string
    {
        return str_replace('.', ',', $value);
    }

    private function renderTimelines(): void
    {
        foreach ($this->select('ol.timeline, ul.timeline') as $timeline) {
            $list = $this->document->createElement('ol');
            $list->setAttribute('class', 'zeitstrahl');
            foreach ($timeline->children as $item) {
                if ($item->localName === 'li') {
                    $list->appendChild($this->buildTimelineItem($item));
                }
            }

            $this->hasComponents = true;
            $timeline->replaceWith($list);
        }
    }

    private function buildTimelineItem(Element $item): Element
    {
        $nodes = iterator_to_array($item->childNodes, false);

        // Das Datum ist ein <time> als erstes Kind; davor darf nur Leerraum stehen.
        $dateNode = null;
        $restStart = 0;
        foreach ($nodes as $position => $node) {
            if ($node instanceof Element) {
                if ($node->localName === 'time') {
                    $dateNode = $node;
                    $restStart = $position + 1;
                }

                break;
            }
            if (trim((string) $node->textContent) !== '') {
                break;
            }
        }

        $date = $this->document->createElement('span');
        $date->setAttribute('class', 'zeitstrahl-datum');
        if ($dateNode !== null) {
            $date->appendChild($dateNode);
        }

        $text = $this->document->createElement('div');
        $text->setAttribute('class', 'zeitstrahl-text');
        foreach (array_slice($nodes, $restStart) as $node) {
            $text->appendChild($node);
        }
        $this->trimLeadingSpace($text);

        $entry = $this->document->createElement('li');
        if ($item->classList->contains('highlight')) {
            $entry->setAttribute('class', 'markiert');
        }
        $entry->appendChild($date);
        $entry->appendChild($text);

        return $entry;
    }

    private function trimLeadingSpace(Element $element): void
    {
        $first = $element->firstChild;
        if ($first === null || $first instanceof Element) {
            return;
        }

        $trimmed = ltrim((string) $first->textContent);
        if ($trimmed === '') {
            $first->remove();
            $this->trimLeadingSpace($element);

            return;
        }
        $first->textContent = $trimmed;
    }

    private function renderAccordions(): void
    {
        foreach ($this->select('div.accordion') as $accordion) {
            $this->accordionCount++;
            $accordion->setAttribute('class', 'akkordeon');
            foreach ($accordion->children as $child) {
                if ($child->localName === 'details') {
                    $child->setAttribute('name', 'akkordeon-' . $this->accordionCount);
                }
            }
            $this->hasComponents = true;
        }
    }

    private function renderEmbeds(): void
    {
        foreach ($this->select('figure[data-embed][data-id]') as $figure) {
            $provider = (string) $figure->getAttribute('data-embed');
            $label = self::EMBED_LABELS[$provider] ?? null;
            if ($label === null) {
                $figure->remove();

                continue;
            }

            $embed = $this->document->createElement('figure');
            $embed->setAttribute('class', 'einbettung');
            $embed->setAttribute('data-embed', $provider);
            $embed->setAttribute('data-id', (string) $figure->getAttribute('data-id'));

            $button = $this->document->createElement('button');
            $button->setAttribute('type', 'button');
            $button->setAttribute('class', 'einbettung-start');
            $button->appendChild($this->document->createTextNode('Video laden '));
            $note = $this->document->createElement('small');
            $note->textContent = '(' . $label . ' – erst nach Klick werden Daten übertragen)';
            $button->appendChild($note);
            $embed->appendChild($button);

            $existingCaption = $this->firstChildNamed($figure, 'figcaption');
            if ($existingCaption !== null) {
                $embed->appendChild($existingCaption);
            }

            $figure->replaceWith($embed);
        }
    }

    /** @param list<array<string, mixed>> $sources Quellen mit `ref` und `nr` */
    private function renderCitations(array $sources): void
    {
        $numberByRef = [];
        foreach ($sources as $source) {
            $numberByRef[(string) $source['ref']] = (int) $source['nr'];
        }

        foreach ($this->select('a[data-cite]') as $anchor) {
            $refs = explode(',', (string) $anchor->getAttribute('data-cite'));
            $anchor->removeAttribute('data-cite');

            $insertBefore = $anchor->nextSibling;
            $isFirstMark = true;
            foreach ($refs as $ref) {
                if (!isset($numberByRef[$ref])) {
                    continue;
                }
                $anchor->parentNode?->insertBefore($this->buildCitationMark($numberByRef[$ref], !$isFirstMark), $insertBefore);
                $isFirstMark = false;
            }

            if (!$anchor->hasAttribute('href')) {
                $this->unwrap($anchor);
            }
        }
    }

    /**
     * Ab dem zweiten Beleg einer Folge steht ein Komma vor der Zahl, sonst läse sich 1, 4, 6 als Fußnote 146.
     * Es steht im Link, damit es dessen Schrift trägt; Vorleser hören nur das aria-label.
     */
    private function buildCitationMark(int $number, bool $hasSeparator): Element
    {
        $mark = $this->document->createElement('sup');
        $mark->setAttribute('class', 'beleg');
        $link = $this->document->createElement('a');
        $link->setAttribute('href', '#quelle-' . $number);
        $link->setAttribute('aria-label', 'Quelle ' . $number);
        $link->textContent = ($hasSeparator ? ',' : '') . $number;
        $mark->appendChild($link);

        return $mark;
    }

    /** @return list<array{id: string, text: string}> */
    private function addHeadingIds(): array
    {
        $toc = [];
        $used = [];
        foreach ($this->select('h2') as $heading) {
            $text = $this->normalizedText($heading);
            $base = Text::slugify($text);
            $id = $base;
            for ($counter = 2; isset($used[$id]); $counter++) {
                $id = $base . '-' . $counter;
            }
            $used[$id] = true;

            $heading->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text];
        }

        return $toc;
    }

    /** @return list<string> */
    private function headingTexts(): array
    {
        $texts = [];
        foreach ($this->select('h2, h3') as $heading) {
            $texts[] = $this->normalizedText($heading);
        }

        return $texts;
    }

    private function addShortTitles(): void
    {
        foreach ($this->select('aside.tldr, div.tldr, .sidenote') as $element) {
            $isSidenote = $element->classList->contains('sidenote') && !$element->classList->contains('tldr');
            $title = $this->document->createElement('b');
            $title->setAttribute('class', 'kurz-titel');
            $title->textContent = $isSidenote ? 'Randnotiz' : 'Kurz gesagt';
            $element->insertBefore($title, $element->firstChild);
        }
    }

    /** @param array<string, mixed> $medium */
    private function buildPicture(array $medium, string $sizes, bool $isEager): Element
    {
        $variants = varianten($medium);

        $picture = $this->document->createElement('picture');
        if ($variants['webp'] !== '') {
            $source = $this->document->createElement('source');
            $source->setAttribute('type', 'image/webp');
            $source->setAttribute('srcset', $variants['webp']);
            $source->setAttribute('sizes', $sizes);
            $picture->appendChild($source);
        }

        $image = $this->document->createElement('img');
        $image->setAttribute('src', $variants['src']);
        if ($variants['fallback'] !== '') {
            $image->setAttribute('srcset', $variants['fallback']);
            $image->setAttribute('sizes', $sizes);
        }
        $image->setAttribute('width', (string) $variants['w']);
        $image->setAttribute('height', (string) $variants['h']);
        $image->setAttribute('alt', (string) $medium['alt']);
        $image->setAttribute('loading', $isEager ? 'eager' : 'lazy');
        $image->setAttribute('decoding', 'async');
        $picture->appendChild($image);

        return $picture;
    }

    /** @param array<string, mixed> $medium */
    private function buildCaption(?Element $existing, array $medium): ?Element
    {
        $caption = $this->document->createElement('figcaption');
        if ($existing !== null) {
            while ($existing->firstChild !== null) {
                $caption->appendChild($existing->firstChild);
            }
        } elseif (trim((string) $medium['caption']) !== '') {
            $caption->textContent = trim((string) $medium['caption']);
        }

        $credit = $this->buildCredit($medium, self::KIND_LABELS[$medium['kind']] ?? 'Foto');
        if ($credit !== null) {
            if ($caption->firstChild !== null) {
                $caption->appendChild($this->document->createTextNode(' '));
            }
            $caption->appendChild($credit);
        }

        return $caption->firstChild === null ? null : $caption;
    }

    /**
     * Nachweis „Foto: Urheber · Lizenz · Quelle“; Links nur zu http(s)-Adressen.
     *
     * @param array<string, mixed> $medium
     */
    private function buildCredit(array $medium, string $label): ?Element
    {
        $parts = [];
        $credit = trim((string) $medium['credit']);
        if ($credit !== '') {
            $parts[] = $this->document->createTextNode($label . ': ' . $credit);
        }

        $license = trim((string) $medium['license']);
        if ($license !== '') {
            $parts[] = $this->buildLink($license, (string) $medium['license_url'], 'license');
        }

        $sourceUrl = (string) $medium['source_url'];
        if ($this->isWebAddress($sourceUrl)) {
            $parts[] = $this->buildLink('Quelle', $sourceUrl, null);
        }

        if ($parts === []) {
            return null;
        }

        $span = $this->document->createElement('span');
        $span->setAttribute('class', 'nachweis');
        foreach ($parts as $position => $part) {
            if ($position > 0) {
                $span->appendChild($this->document->createTextNode(' · '));
            }
            $span->appendChild($part);
        }

        return $span;
    }

    /** Ohne gültige Adresse bleibt nur der Text. */
    private function buildLink(string $text, string $address, ?string $rel): Node
    {
        if (!$this->isWebAddress($address)) {
            return $this->document->createTextNode($text);
        }

        $link = $this->document->createElement('a');
        $link->setAttribute('href', $address);
        if ($rel !== null) {
            $link->setAttribute('rel', $rel);
        }
        $link->textContent = $text;

        return $link;
    }

    private function isWebAddress(string $address): bool
    {
        return str_starts_with($address, 'https://') || str_starts_with($address, 'http://');
    }

    private function dropMissing(Element $figure, string $uid): void
    {
        Log::error('Medium des Artikels fehlt in den Seitendaten', ['post' => $this->postSlug, 'uid' => $uid]);
        $figure->remove();
    }

    private function firstChildNamed(Element $parent, string $name): ?Element
    {
        foreach ($parent->children as $child) {
            if ($child->localName === $name) {
                return $child;
            }
        }

        return null;
    }

    private function unwrap(Element $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function normalizedText(Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $element->textContent));
    }

    /** @return list<Element> eine feste Liste, damit Umbauen die Suche nicht stört */
    private function select(string $selector): array
    {
        return iterator_to_array($this->document->querySelectorAll($selector), false);
    }
}
