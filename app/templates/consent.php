<?php

declare(strict_types=1);

use function Dispatch\Site\e;

// Gast-Zustand der Zustimmungsseite; nach der Anmeldung ersetzt `zustimmung.js` den Inhalt von `#seite`.
// Die Stile der Zustimmung liegen im Redaktionsordner und werden hier schon für den Gast geladen.
$editorialStyle = isset($assets['redaktion']) ? dirname((string) $assets['redaktion']) . '/redaktion.css' : '';
?>
<?php if ($editorialStyle !== '') { ?>
<link rel="stylesheet" href="<?= e($editorialStyle) ?>">
<?php } ?>
<div class="zust-seite" id="seite">
  <section class="zust" aria-live="polite">
    <p class="klein">Verbindung anfragen</p>
    <h1>Eine App möchte auf deinen Blog zugreifen</h1>
    <p>Nur du als Herausgeber kannst das erlauben. Melde dich an, dann siehst du, wer fragt und was die App dürfte.</p>
    <div class="aktionen"><button type="button" class="knopf primaer" id="zustimmung-anmelden">Anmelden</button></div>
  </section>
</div>
