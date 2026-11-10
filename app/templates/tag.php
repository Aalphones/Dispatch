<?php

declare(strict_types=1);

use function Dispatch\Site\e;

/**
 * @var array{name: string, slug: string, url: string, count: int} $tag
 * @var list<array{name: string, slug: string, url: string, count: int}> $companions häufigste Mit-Schlagwörter
 * @var list<array<string, mixed>> $posts Karten dieser Seite
 * @var int $total Artikel mit diesem Schlagwort
 * @var int $seite
 * @var int $gesamt
 */
?>
<div class="filterleiste">
  <div class="wrap">
    <div class="filterzeile"><b>Gefiltert nach</b>
      <ul class="chips"><li><a class="chip tag aktiv" href="/" aria-label="Filter <?= e($tag['name']) ?> entfernen"><?= e($tag['name']) ?> <span class="x" aria-hidden="true">×</span></a></li></ul>
    </div>
<?php if ($companions !== []) { ?>
    <div class="filterzeile"><b>Hinzufügen</b>
      <ul class="chips">
<?php foreach ($companions as $companion) { ?>
        <li><a class="chip tag" href="/suche/?tag=<?= e(rawurlencode($tag['slug'])) ?>&amp;tag=<?= e(rawurlencode($companion['slug'])) ?>"><?= e($companion['name']) ?> <span class="zahl"><?= (int) $companion['together'] ?></span></a></li>
<?php } ?>
      </ul>
    </div>
<?php } ?>
  </div>
</div>
<div class="wrap">
  <header class="seitenkopf"><span class="klein dach">Schlagwort</span><h1>#<?= e($tag['name']) ?></h1></header>
  <p class="ergebniszahl"><span><b><?= $total ?></b> Artikel, neueste zuerst</span></p>
  <div class="raster">
<?php foreach ($posts as $post) { ?>
<?= $this->partial('card', ['post' => $post]) ?>
<?php } ?>
  </div>
<?= $this->partial('pagination', ['basis' => $tag['url'], 'seite' => $seite, 'gesamt' => $gesamt]) ?>
</div>
