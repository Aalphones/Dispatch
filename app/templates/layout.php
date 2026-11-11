<?php

declare(strict_types=1);

use Dispatch\Site\Seo;
use Dispatch\Site\SiteData;

use function Dispatch\Site\datum;
use function Dispatch\Site\e;

/**
 * @var string $content fertiges HTML der Seitenvorlage
 * @var array<string, mixed> $meta title, description, canonical, robots, bodyPage, activeCategory, scripts
 * @var SiteData $site
 * @var array<string, string> $assets Name → URL
 * @var string $baseUrl
 */

$siteTitle = (string) $site->settings['site_title'];
$siteTagline = (string) $site->settings['site_tagline'];
$description = trim((string) ($meta['description'] ?? ''));
if ($description === '') {
    $description = trim((string) $site->settings['site_description']);
}
if ($description === '') {
    $description = $siteTagline;
}
$meta['description'] = $description;
$canonical = (string) ($meta['canonical'] ?? '');
$robots = (string) ($meta['robots'] ?? 'index, follow');
$activeCategory = (string) ($meta['activeCategory'] ?? '');
$scripts = (array) ($meta['scripts'] ?? []);
// Die Seitenvorlage trägt die einzige h1; nur auf der ersten Startseite ist das der Seitenname im Kopf.
$nameTag = ($meta['nameAlsH1'] ?? false) === true ? 'h1' : 'p';

$mainCategories = [];
foreach ($site->categories as $category) {
    if ($category['parent_id'] === null) {
        $mainCategories[] = $category;
    }
}
$mainCategories = array_slice($mainCategories, 0, 6);
$topTags = array_slice($site->tags, 0, 10);
?>
<!doctype html>
<html lang="de" data-suche="<?= e($assets['suche'] ?? '') ?>" data-zaehler="<?= e($assets['zaehler'] ?? '') ?>" data-artikel="<?= e($assets['artikel'] ?? '') ?>" data-redaktion="<?= e($assets['redaktion'] ?? '') ?>"<?php if (isset($assets['suchindex'])) { ?> data-suchindex="<?= e($assets['suchindex']) ?>"<?php } ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((string) ($meta['title'] ?? $siteTitle)) ?></title>
<?php if ($description !== '') { ?>
<meta name="description" content="<?= e($description) ?>">
<?php } ?>
<?php if ($canonical !== '') { ?>
<link rel="canonical" href="<?= e($baseUrl . $canonical) ?>">
<?php } ?>
<meta name="robots" content="<?= e($robots) ?>">
<?php foreach (Seo::headTags($meta, $site, $baseUrl) as $headTag) { ?>
<<?= $headTag['tag'] ?><?php foreach ($headTag['attributes'] as $attribute => $value) { ?> <?= $attribute ?>="<?= e($value) ?>"<?php } ?>>
<?php } ?>
<link rel="stylesheet" href="<?= e($assets['style']) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= e($siteTitle) ?>" href="/feed.xml">
<?php foreach (Seo::jsonLd($meta, $site, $baseUrl) as $jsonLd) { ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<?php } ?>
</head>
<body data-seite="<?= e((string) ($meta['bodyPage'] ?? '')) ?>"<?php if (($meta['bodyPage'] ?? '') === 'artikel' && isset($meta['post']['id'])) { ?> data-post-id="<?= (int) $meta['post']['id'] ?>"<?php } ?>>
<div class="fortschritt" aria-hidden="true"></div>
<a class="sprung" href="#inhalt">Zum Inhalt</a>

<header>
  <div class="leiste">
    <div class="wrap">
      <span class="klein ausgabe-zeile"><span class="ausgabe" title="Laufende Nummer: so viele Artikel sind bisher erschienen">Ausgabe Nr. <?= $site->issueNumber ?></span><span class="ausgabe-datum"> · <?= e(datum(gmdate('Y-m-d H:i:s'))) ?></span></span>
      <div class="kopf-rechts">
        <form class="suche" id="suche" action="/suche/" role="search">
          <label class="suche-feld">
            <input id="suchfeld" name="q" type="search" placeholder="Suchen …" autocomplete="off" aria-label="Artikel, Kategorien und Schlagwörter durchsuchen" aria-controls="panel" aria-keyshortcuts="Control+K /">
            <kbd class="taste">Strg K</kbd>
            <button type="button" class="suche-zu" id="suche-zu">Abbrechen</button>
          </label>
          <div class="panel" id="panel" role="listbox" aria-label="Vorschläge"></div>
        </form>
        <button type="button" class="konto nur-gast" id="anmelden" title="Anmelden (nur Herausgeber)"><span class="avatar"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span><span class="text">Anmelden</span></button>
      </div>
    </div>
  </div>
  <div class="titelkopf wrap"><<?= $nameTag ?> class="name"><a href="/"><?= e($siteTitle) ?></a></<?= $nameTag ?>></div>
  <nav class="nav" aria-label="Kategorien">
    <ul>
<?php foreach ($mainCategories as $category) { ?>
      <li><a href="<?= e($category['url']) ?>"<?php if ($category['slug'] === $activeCategory) { ?> aria-current="page"<?php } ?>><?= e($category['name']) ?></a></li>
<?php } ?>
      <li><a href="/kategorien/" class="alle">Alle Kategorien</a></li>
    </ul>
  </nav>
</header>

<main id="inhalt">
<?= $content ?>
</main>

<footer class="fuss">
  <div class="wrap">
    <div class="spalten">
<?php if ($topTags !== []) { ?>
      <section aria-labelledby="fh-tags">
        <h2 id="fh-tags">Häufige Schlagwörter</h2>
        <ul class="chips">
<?php foreach ($topTags as $tag) { ?>
          <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?> <span class="zahl"><?= $tag['count'] ?></span></a></li>
<?php } ?>
        </ul>
      </section>
<?php } ?>
      <section aria-labelledby="fh-seiten">
        <h2 id="fh-seiten"><?= e($siteTitle) ?></h2>
        <nav aria-labelledby="fh-seiten"><a href="/ueber/">Über</a><a href="/kategorien/">Alle Kategorien</a><a href="/feed.xml">RSS-Feed</a><a href="/impressum/">Impressum</a><a href="/datenschutz/">Datenschutz</a></nav>
      </section>
    </div>
    <div class="unten"><span><?= e($siteTitle) ?> · <?= e($siteTagline) ?></span></div>
  </div>
</footer>

<dialog id="login" aria-labelledby="login-titel">
  <form id="login-form">
    <div class="dlg-kopf"><h2 id="login-titel">Anmelden</h2><button type="button" class="zu" data-close aria-label="Schließen">✕</button></div>
    <div class="dlg-rumpf">
      <label class="feld">Benutzername<input name="user" autocomplete="username" required></label>
      <label class="feld">Passwort<input name="password" type="password" autocomplete="current-password" required></label>
      <p class="hinweis" id="login-fehler" role="alert" hidden></p>
      <p class="hinweis">Nur für den Herausgeber. Danach erscheinen die Redaktionsfunktionen direkt in der Website.</p>
    </div>
    <div class="dlg-fuss"><button type="button" class="knopf" data-close>Abbrechen</button><button type="submit" class="knopf primaer">Anmelden</button></div>
  </form>
</dialog>

<script type="module" src="<?= e($assets['site']) ?>"></script>
<?php foreach ($scripts as $script) { ?>
<script type="module" src="<?= e((string) $script) ?>"></script>
<?php } ?>
</body>
</html>
