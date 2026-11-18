<?php

declare(strict_types=1);

use function Dispatch\Site\datum;
use function Dispatch\Site\dachzeile;
use function Dispatch\Site\e;
use function Dispatch\Site\picture;
use function Dispatch\Site\quellenText;

/**
 * @var array<string, mixed>|null $aufmacher neuester Artikel, nur auf Seite 1
 * @var list<array<string, mixed>> $posts Karten dieser Seite
 * @var int $seite
 * @var int $gesamt
 * @var string $aboutText site_description, sonst site_tagline (Karte „Über DISPATCH“)
 * @var list<array{name: string, slug: string, url: string, count: int}> $topTags die zehn häufigsten Schlagwörter
 * @var list<array<string, mixed>> $topCategories Hauptkategorien mit mindestens einem Artikel (Karte „Kategorien“)
 */
?>
<div class="wrap">
<?php if ($seite > 1) { ?>
  <header class="seitenkopf"><h1>Ältere Artikel – Seite <?= $seite ?></h1></header>
<?php } ?>
<?php if ($aufmacher === null && $posts === []) { ?>
  <header class="seitenkopf"><p>Noch keine Artikel erschienen.</p></header>
<?php } ?>
<?php $mitSeite = $seite === 1; ?>
  <div class="home-layout<?= $mitSeite ? ' mit-seite' : '' ?>">
  <div class="home-main">
<?php if ($aufmacher !== null) { ?>
<?php $cover = $aufmacher['cover']; $sourceCount = count($aufmacher['sources']); ?>
  <article class="aufmacher" data-post-id="<?= (int) $aufmacher['id'] ?>">
<?php if ($cover !== null) { ?>
    <a href="<?= e($aufmacher['url']) ?>" class="foto" aria-hidden="true" tabindex="-1"><?= picture(['alt' => ''] + $cover, '(max-width: 46rem) 100vw, 42rem', true) ?></a>
<?php } ?>
    <div>
      <span class="klein dach"><?= e(dachzeile($aufmacher)) ?></span>
      <h2><a href="<?= e($aufmacher['url']) ?>"><?= e($aufmacher['title']) ?></a></h2>
<?php if (trim((string) $aufmacher['excerpt']) !== '') { ?>
      <p class="teaser"><?= e((string) $aufmacher['excerpt']) ?></p>
<?php } ?>
      <p class="meta"><?= e(datum((string) $aufmacher['published_at'])) ?> · <?= (int) $aufmacher['reading_minutes'] ?> Min. Lesezeit<?php if ($sourceCount > 0) { ?> · <?= e(quellenText($sourceCount)) ?><?php } ?></p>
      <a class="weiter" href="<?= e($aufmacher['url']) ?>">Weiterlesen<span class="sr-nur">: <?= e($aufmacher['title']) ?></span> <span aria-hidden="true">→</span></a>
    </div>
  </article>
<?php } ?>
<?php if ($posts !== []) { ?>
  <div class="raster">
<?php foreach ($posts as $post) { ?>
<?= $this->partial('card', ['post' => $post]) ?>
<?php } ?>
  </div>
<?php } ?>
<?= $this->partial('pagination', ['basis' => '/', 'seite' => $seite, 'gesamt' => $gesamt]) ?>
  </div>
<?php if ($mitSeite) { ?>
  <aside class="home-sidebar" aria-label="Über die Seite">
<?php if ($topCategories !== []) { ?>
    <section class="side-karte">
      <h2>Kategorien</h2>
      <ul class="kat-liste">
<?php foreach ($topCategories as $category) { ?>
        <li><a href="<?= e($category['url']) ?>"><span><?= e($category['name']) ?></span><span class="zahl"><?= (int) $category['post_count'] ?></span></a></li>
<?php } ?>
      </ul>
      <a class="weiter" href="/kategorien/">Alle Kategorien <span aria-hidden="true">→</span></a>
    </section>
<?php } ?>
    <section class="side-karte">
      <h2><?php if (($assets['logoIcon'] ?? '') !== '') { ?><img src="<?= e($assets['logoIcon']) ?>" alt="" width="128" height="128"><?php } ?>Über DISPATCH</h2>
<?php if ($aboutText !== '') { ?>
      <p><?= e($aboutText) ?></p>
<?php } ?>
      <a class="weiter" href="/ueber/">Mehr über DISPATCH <span aria-hidden="true">→</span></a>
    </section>
<?php if ($topTags !== []) { ?>
    <section class="side-karte">
      <h2>Beliebte Schlagwörter</h2>
      <ul class="chips">
<?php foreach ($topTags as $tag) { ?>
        <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?> <span class="zahl"><?= (int) $tag['count'] ?></span></a></li>
<?php } ?>
      </ul>
    </section>
<?php } ?>
  </aside>
<?php } ?>
  </div>
</div>
