<?php

declare(strict_types=1);

use function Dispatch\Site\e;

/** @var list<array{name: string, url: string}> $krumen Start zuerst, die aktuelle Seite zuletzt */
$last = count($krumen) - 1;
?>
<nav class="krumen" aria-label="Brotkrumen">
  <ol>
<?php foreach ($krumen as $index => $krume) { ?>
<?php if ($index === $last) { ?>
    <li><span aria-current="page"><?= e($krume['name']) ?></span></li>
<?php } else { ?>
    <li><a href="<?= e($krume['url']) ?>"><?= e($krume['name']) ?></a></li>
<?php } ?>
<?php } ?>
  </ol>
</nav>
