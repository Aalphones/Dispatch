<?php

declare(strict_types=1);

use function Dispatch\Site\e;

/** @var list<array<string, mixed>> $posts neueste zuerst */
?>
<div class="wrap">
<?php if ($posts === []) { ?>
  <p>Noch keine Artikel veröffentlicht.</p>
<?php } else { ?>
  <h1><?= e($posts[0]['title']) ?></h1>
  <ul>
<?php foreach (array_slice($posts, 1) as $post) { ?>
    <li><a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a></li>
<?php } ?>
  </ul>
<?php } ?>
</div>
