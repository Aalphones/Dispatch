<?php

declare(strict_types=1);

use function Dispatch\Site\datum;
use function Dispatch\Site\dachzeile;
use function Dispatch\Site\e;
use function Dispatch\Site\picture;
use function Dispatch\Site\quellenText;

/**
 * @var array<string, mixed> $post PostView
 * @var array{html: string, toc: list<array{id: string, text: string}>} $rendered
 * @var list<array{name: string, url: string}> $krumen
 * @var list<array<string, mixed>> $related drei Karten für „Mehr dazu“
 * @var array<string, mixed>|null $older vorheriger (älterer) Artikel
 * @var array<string, mixed>|null $newer nächster (neuerer) Artikel
 */
$cover = $post['cover'];
$toc = $rendered['toc'];
$hasToc = count($toc) >= 2;
$sources = $post['sources'];
$tags = $post['tags'];
$publishedLabel = datum((string) $post['published_at']);
$updatedLabel = datum((string) $post['updated_at']);
$accent = trim((string) $post['accent']);
$disclosure = trim((string) $site->settings['ai_disclosure_text']);
$authorName = trim((string) $site->settings['author_name']);
$subtitle = trim((string) $post['subtitle']);
$style = $accent === '' ? '' : ' style="--accent:' . e($accent) . '"';
?>
<article class="artikel wrap layout-<?= e((string) $post['layout']) ?>" data-post-id="<?= (int) $post['id'] ?>" data-post-slug="<?= e($post['slug']) ?>"<?= $style ?>>
<?= $this->partial('breadcrumbs', ['krumen' => $krumen]) ?>

  <header class="artikelkopf">
    <span class="klein dach"><?= e(dachzeile($post)) ?></span>
    <h1><?= e($post['title']) ?></h1>
<?php if ($subtitle !== '') { ?>
    <p class="unterzeile"><?= e($subtitle) ?></p>
<?php } ?>
    <div class="autor">
<?php if ($authorName !== '') { ?>
      <span>Von <?= e($authorName) ?></span>
<?php } ?>
      <span><?= e($publishedLabel) ?></span>
<?php if ($updatedLabel !== $publishedLabel) { ?>
      <span>aktualisiert <?= e($updatedLabel) ?></span>
<?php } ?>
    </div>
    <div class="badges">
<?php if ($post['ai_assisted']) { ?>
      <span class="badge ki" title="Recherchiert und entworfen von einem KI-Agenten, vom Herausgeber gelesen und freigegeben">KI-recherchiert</span>
<?php } ?>
<?php if ($sources !== []) { ?>
      <a class="badge quellen" href="#quellen"><?= e(quellenText(count($sources))) ?></a>
<?php } ?>
      <span class="badge zeit"><?= (int) $post['reading_minutes'] ?> Min. Lesezeit</span>
      <button class="badge fokus" id="fokus-an" type="button" hidden title="Nur der Text, im Vollbild" aria-keyshortcuts="F">Fokus <kbd>F</kbd></button>
    </div>
  </header>

<?php if ($cover !== null) { ?>
<?php $credit = trim((string) $cover['credit']); $license = trim((string) $cover['license']); $caption = trim((string) $cover['caption']); ?>
  <figure class="titelbild">
    <div class="foto"><?= picture($cover, '(max-width: 46rem) 100vw, 64rem', true) ?></div>
<?php if ($caption !== '' || $credit !== '' || $license !== '') { ?>
    <figcaption><?= e($caption) ?><?php if ($credit !== '' || $license !== '') { ?> <span class="nachweis"><?= $credit !== '' ? e('Foto: ' . $credit) : '' ?><?= $credit !== '' && $license !== '' ? ' · ' : '' ?><?php if ($license !== '') { ?><?php if (preg_match('#^https?://#i', (string) $cover['license_url']) === 1) { ?><a href="<?= e((string) $cover['license_url']) ?>" rel="license"><?= e($license) ?></a><?php } else { ?><?= e($license) ?><?php } ?><?php } ?></span><?php } ?></figcaption>
<?php } ?>
  </figure>
<?php } ?>

  <div class="koerper">
    <aside class="seitenspalte" id="seitenspalte">
<?php if ($hasToc) { ?>
      <nav class="iv" aria-label="Inhaltsverzeichnis">
        <span class="klein">Inhalt</span>
        <ol>
<?php foreach ($toc as $entry) { ?>
          <li><a href="#<?= e($entry['id']) ?>"><?= e($entry['text']) ?></a></li>
<?php } ?>
        </ol>
      </nav>
<?php } ?>
<?php if ($tags !== []) { ?>
      <nav aria-label="Schlagwörter dieses Artikels">
        <span class="klein">Schlagwörter</span>
        <ul class="chips">
<?php foreach ($tags as $tag) { ?>
          <li><a class="chip tag" href="<?= e($tag['url']) ?>" rel="tag"><?= e($tag['name']) ?></a></li>
<?php } ?>
        </ul>
      </nav>
<?php } ?>
    </aside>

    <div class="text">
<?php if ($hasToc) { ?>
      <details class="iv-mobil nur-schmal" id="iv-mobil">
        <summary><span class="klein">Inhalt</span><span class="jetzt" id="iv-jetzt"><?= e($toc[0]['text']) ?></span><span class="pfeil" aria-hidden="true">▾</span></summary>
        <ol>
<?php foreach ($toc as $entry) { ?>
          <li><a href="#<?= e($entry['id']) ?>"><?= e($entry['text']) ?></a></li>
<?php } ?>
        </ol>
<?php if ($tags !== []) { ?>
        <div class="iv-tags"><span class="klein">Schlagwörter</span><ul class="chips">
<?php foreach ($tags as $tag) { ?>
          <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?></a></li>
<?php } ?>
        </ul></div>
<?php } ?>
      </details>
<?php } ?>
<?php if ($tags !== []) { ?>
      <ul class="chips nur-schmal kopf-tags" aria-label="Schlagwörter dieses Artikels">
<?php foreach ($tags as $tag) { ?>
        <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?></a></li>
<?php } ?>
      </ul>
<?php } ?>

<?= $rendered['html'] ?>

<?php if ($post['ai_assisted'] && $disclosure !== '') { ?>
      <aside class="transparenz"><div><strong>Wie dieser Artikel entstand.</strong> <?= e($disclosure) ?></div></aside>
<?php } ?>

<?php if ($sources !== []) { ?>
      <section class="quellen" id="quellen" aria-labelledby="quellen-titel">
        <h2 class="abschnitt-titel" id="quellen-titel">Quellen</h2>
        <ol>
<?php foreach ($sources as $source) { ?>
<?php
    $author = trim((string) $source['author']);
    $address = (string) $source['url'];
    $isLinked = preg_match('#^https?://#i', $address) === 1;
    $details = array_filter([
        trim((string) $source['publisher']),
        trim((string) $source['published']),
        $source['accessed'] === null ? '' : 'abgerufen ' . datum((string) $source['accessed']),
    ], static fn (string $part): bool => $part !== '');
?>
          <li id="quelle-<?= (int) $source['nr'] ?>"><?php if ($author !== '') { ?><?= e($author) ?>: <?php } ?><?php if ($isLinked) { ?><a href="<?= e($address) ?>" rel="noopener">„<?= e((string) $source['title']) ?>“</a><?php } else { ?>„<?= e((string) $source['title']) ?>“<?php } ?><?php if ($details !== []) { ?>, <?= e(implode(', ', $details)) ?><?php } ?></li>
<?php } ?>
        </ol>
      </section>
<?php } ?>

<?php if ($tags !== []) { ?>
      <div class="tags-unten nur-schmal">
        <h2 class="abschnitt-titel">Schlagwörter</h2>
        <ul class="chips">
<?php foreach ($tags as $tag) { ?>
          <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?></a></li>
<?php } ?>
        </ul>
      </div>
<?php } ?>

<?php if ($older !== null || $newer !== null) { ?>
      <nav class="vornach" aria-label="Weitere Artikel">
        <div><?php if ($older !== null) { ?><span class="klein">← Vorheriger</span><a href="<?= e($older['url']) ?>" rel="prev"><?= e($older['title']) ?></a><?php } ?></div>
        <div><?php if ($newer !== null) { ?><span class="klein">Nächster →</span><a href="<?= e($newer['url']) ?>" rel="next"><?= e($newer['title']) ?></a><?php } ?></div>
      </nav>
<?php } ?>
    </div>
    <div aria-hidden="true"></div>
  </div>

<?php if ($related !== []) { ?>
  <section class="mehr-dazu" aria-labelledby="mehr-dazu-titel">
    <h2 class="abschnitt-titel" id="mehr-dazu-titel">Mehr dazu</h2>
    <div class="raster">
<?php foreach ($related as $card) { ?>
<?= $this->partial('card', ['post' => $card]) ?>
<?php } ?>
    </div>
  </section>
<?php } ?>
</article>
