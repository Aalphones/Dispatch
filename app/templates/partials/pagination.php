<?php

declare(strict_types=1);

use function Dispatch\Site\e;
use function Dispatch\Site\seitenUrl;

/**
 * @var string $basis Adresse der Liste mit abschließendem Schrägstrich
 * @var int $seite aktuelle Seite
 * @var int $gesamt Zahl der Seiten
 */
if ($gesamt <= 1) {
    return;
}

// Bis sieben Seiten alle Nummern, darüber: erste, letzte und die Nachbarn der aktuellen — Lücken als „…“.
$numbers = range(1, $gesamt);
if ($gesamt > 7) {
    $numbers = array_values(array_unique(array_filter(
        [1, $seite - 1, $seite, $seite + 1, $gesamt],
        static fn (int $number): bool => $number >= 1 && $number <= $gesamt
    )));
    sort($numbers);
}
?>
<nav class="seiten" aria-label="Seiten">
<?php if ($seite <= 1) { ?>
  <span class="weiter-knopf zurueck" aria-disabled="true">← Neuere</span>
<?php } else { ?>
  <a class="weiter-knopf zurueck" href="<?= e(seitenUrl($basis, $seite - 1)) ?>" rel="prev">← Neuere</a>
<?php } ?>
  <ol>
<?php $previous = 0; ?>
<?php foreach ($numbers as $number) { ?>
<?php if ($number - $previous > 1) { ?>
    <li aria-hidden="true">…</li>
<?php } ?>
    <li><a href="<?= e(seitenUrl($basis, $number)) ?>"<?php if ($number === $seite) { ?> aria-current="page"<?php } ?>><?= $number ?></a></li>
<?php $previous = $number; ?>
<?php } ?>
  </ol>
  <span class="stand">Seite <?= $seite ?> von <?= $gesamt ?></span>
<?php if ($seite >= $gesamt) { ?>
  <span class="weiter-knopf" aria-disabled="true">Ältere →</span>
<?php } else { ?>
  <a class="weiter-knopf" href="<?= e(seitenUrl($basis, $seite + 1)) ?>" rel="next">Ältere →</a>
<?php } ?>
</nav>
