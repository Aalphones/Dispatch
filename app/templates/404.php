<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $posts die neuesten Artikel */
?>
<div class="wrap">
  <header class="seitenkopf">
    <h1>Seite nicht gefunden</h1>
    <p>Diese Adresse gibt es nicht – vielleicht ist der Artikel umgezogen oder nicht mehr erschienen.</p>
    <p><a href="/">Zur Startseite</a> · <a href="/kategorien/">Alle Kategorien</a></p>
  </header>
<?php if ($posts !== []) { ?>
  <div class="raster">
<?php foreach ($posts as $post) { ?>
<?= $this->partial('card', ['post' => $post]) ?>
<?php } ?>
  </div>
<?php } ?>
</div>
