<?php

declare(strict_types=1);

use function Dispatch\Site\e;
use function Dispatch\Site\picture;

/**
 * @var array<string, mixed> $category CategoryView
 * @var list<array{name: string, url: string}> $krumen
 * @var list<array<string, mixed>> $posts Karten dieser Seite
 * @var int $seite
 * @var int $gesamt
 */
$image = $category['image'];
$count = (int) $category['post_count'];
?>
<div class="wrap">
<?= $this->partial('breadcrumbs', ['krumen' => $krumen]) ?>
  <header class="katkarte gross kopf<?= $image === null ? ' verlauf' : '' ?>" data-category-id="<?= (int) $category['id'] ?>">
<?php if ($image !== null) { ?>
    <?= picture(['alt' => ''] + $image, '(max-width: 46rem) 100vw, 74rem') ?>

<?php } ?>
    <span class="klein"><?= $count ?> Artikel</span>
    <h1><?= e($category['name']) ?></h1>
<?php if (trim((string) $category['description']) !== '') { ?>
    <p><?= e((string) $category['description']) ?></p>
<?php } ?>
  </header>
<?php if ($category['children'] !== []) { ?>
  <ul class="chips unterkategorien" aria-label="Unterkategorien">
<?php foreach ($category['children'] as $child) { ?>
    <li><a class="chip kat" href="<?= e($child['url']) ?>"><?= e($child['name']) ?> <span class="zahl"><?= (int) $child['post_count'] ?></span></a></li>
<?php } ?>
  </ul>
<?php } ?>
<?php if ($posts === []) { ?>
  <p class="hinweis">Noch keine Artikel in dieser Kategorie.</p>
<?php } else { ?>
  <div class="raster">
<?php foreach ($posts as $post) { ?>
<?= $this->partial('card', ['post' => $post]) ?>
<?php } ?>
  </div>
<?php } ?>
<?= $this->partial('pagination', ['basis' => $category['url'], 'seite' => $seite, 'gesamt' => $gesamt]) ?>
</div>
