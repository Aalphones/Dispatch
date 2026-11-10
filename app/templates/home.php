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
 */
?>
<div class="wrap">
<?php if ($seite > 1) { ?>
  <header class="seitenkopf"><h1>Ältere Artikel – Seite <?= $seite ?></h1></header>
<?php } ?>
<?php if ($aufmacher === null && $posts === []) { ?>
  <header class="seitenkopf"><p>Noch keine Artikel erschienen.</p></header>
<?php } ?>
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
