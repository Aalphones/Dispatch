<?php

declare(strict_types=1);

use function Dispatch\Site\datum;
use function Dispatch\Site\dachzeile;
use function Dispatch\Site\e;
use function Dispatch\Site\picture;

/** @var array<string, mixed> $post PostView */
$cover = $post['cover'];
$excerpt = trim((string) $post['excerpt']);
?>
<article class="karte" data-post-id="<?= (int) $post['id'] ?>">
<?php if ($cover !== null) { ?>
  <a class="foto" href="<?= e($post['url']) ?>" tabindex="-1" aria-hidden="true"><?= picture(['alt' => ''] + $cover, '(max-width: 46rem) 6.5rem, (max-width: 62rem) 50vw, 24rem') ?></a>
<?php } ?>
  <span class="klein dach"><?= e(dachzeile($post)) ?></span>
  <h3><a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a></h3>
<?php if ($excerpt !== '') { ?>
  <p class="teaser"><?= e($excerpt) ?></p>
<?php } ?>
  <p class="meta"><?= e(datum((string) $post['published_at'], true)) ?> · <?= (int) $post['reading_minutes'] ?> Min.</p>
</article>
