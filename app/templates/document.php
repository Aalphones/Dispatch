<?php

declare(strict_types=1);

use function Dispatch\Site\e;

/**
 * @var string $title
 * @var string $html fertig umgesetztes und bereinigtes Markdown
 */
?>
<div class="wrap">
  <header class="seitenkopf"><h1><?= e($title) ?></h1></header>
  <div class="text seitentext">
<?= $html ?>
  </div>
</div>
