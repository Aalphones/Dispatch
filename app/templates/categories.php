<?php

declare(strict_types=1);

use function Dispatch\Site\e;
use function Dispatch\Site\picture;

/**
 * @var list<array<string, mixed>> $mainCategories Hauptkategorien nach Position
 * @var array<int, array<string, mixed>> $postsById Artikel nach Nummer, für „Neuester“
 * @var int $subCategoryCount
 * @var int $postCount
 */
?>
<div class="wrap">
  <header class="seitenkopf">
    <span class="klein dach">Übersicht</span>
    <h1>Alle Kategorien</h1>
    <p><?= count($mainCategories) ?> Ressorts · <?= $subCategoryCount ?> Unterkategorien · <?= $postCount ?> Artikel</p>
  </header>
  <div class="katgrid">
<?php foreach ($mainCategories as $index => $category) { ?>
<?php
    $image = $category['image'];
    $count = (int) $category['post_count'];
    $newest = null;
    if ($category['newest_post_id'] !== null) {
        $newest = $postsById[$category['newest_post_id']] ?? null;
    }
    $isLarge = $index < 2;
?>
    <a class="katkarte<?= $isLarge ? ' gross' : '' ?><?= $image === null ? ' verlauf' : '' ?>" href="<?= e($category['url']) ?>" data-category-id="<?= (int) $category['id'] ?>">
<?php if ($image !== null) { ?>
      <?= picture(['alt' => ''] + $image, $isLarge ? '(max-width: 46rem) 100vw, 38rem' : '(max-width: 46rem) 100vw, 25rem') ?>

<?php } ?>
      <span class="klein"><?= $count ?> Artikel</span>
      <h2><?= e($category['name']) ?> <span class="pfeil" aria-hidden="true">→</span></h2>
<?php if (trim((string) $category['description']) !== '') { ?>
      <p><?= e((string) $category['description']) ?></p>
<?php } ?>
<?php if ($category['children'] !== [] || $newest !== null) { ?>
      <div class="mehr"><div>
<?php if ($category['children'] !== []) { ?>
        <div class="unterkat">
<?php foreach ($category['children'] as $child) { ?>
          <span><?= e($child['name']) ?> · <?= (int) $child['post_count'] ?></span>
<?php } ?>
        </div>
<?php } ?>
<?php if ($newest !== null) { ?>
        <div class="neuester">Neuester: <?= e($newest['title']) ?></div>
<?php } ?>
      </div></div>
<?php } ?>
    </a>
<?php } ?>
  </div>
</div>
