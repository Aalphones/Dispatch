<?php

declare(strict_types=1);

use Dispatch\Site\Seo;
use Dispatch\Site\SiteData;

use function Dispatch\Site\e;

/**
 * @var string $content fertiges HTML der Seitenvorlage
 * @var array<string, mixed> $meta title, description, canonical, robots, bodyPage, activeCategory, scripts, styles
 * @var SiteData $site
 * @var array<string, string> $assets Name → URL (Marken-Assets: logoPrimary, logoIcon, logoWordmark, footerMascot, contentBg; leer, wenn die Quelldatei fehlt)
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
// Die Seitenvorlage trägt die einzige h1; nur die erste Startseite hat keine sichtbare und bekommt einen Screenreader-Titel.
$startseiteH1 = ($meta['nameAlsH1'] ?? false) === true;

$mainCategories = [];
foreach ($site->categories as $category) {
    if ($category['parent_id'] === null) {
        $mainCategories[] = $category;
    }
}
// Auf dem Desktop zeigt die Leiste die ersten sechs; im Hamburger-Menü (Tablet, Handy) stehen alle.
$hatSeitenleiste = ($meta['seitenleiste'] ?? false) === true;
$footTags = array_slice($site->tags, 0, 10);
$footAbout = trim((string) $site->settings['site_description']);
if ($footAbout === '') {
    $footAbout = $siteTagline;
}
// Marken-Adressen: ohne Asset leer, die Vorlage zeigt dann statt des Bildes den Namen.
$brand = [];
foreach (['logoPrimary', 'logoIcon', 'logoWordmark', 'footerMascot', 'contentBg'] as $brandKey) {
    $brand[$brandKey] = (string) ($assets[$brandKey] ?? '');
}
?>
<!doctype html>
<html lang="de" data-suche="<?= e($assets['suche'] ?? '') ?>" data-zaehler="<?= e($assets['zaehler'] ?? '') ?>" data-artikel="<?= e($assets['artikel'] ?? '') ?>" data-redaktion="<?= e($assets['redaktion'] ?? '') ?>" data-bausteine="<?= e($assets['bausteine'] ?? '') ?>" data-bausteinestil="<?= e($assets['bausteinestil'] ?? '') ?>" data-diagramme="<?= e($assets['diagramme'] ?? '') ?>" data-diagrammestil="<?= e($assets['diagrammestil'] ?? '') ?>"<?php if (isset($assets['suchindex'])) { ?> data-suchindex="<?= e($assets['suchindex']) ?>"<?php } ?>>
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
<?php foreach ((array) ($meta['styles'] ?? []) as $style) { ?>
<link rel="stylesheet" href="<?= e((string) $style) ?>">
<?php } ?>
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
      <a class="burger" id="burger" href="/kategorien/" aria-label="Kategorien" aria-controls="kat-menue" aria-expanded="false"><svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></a>
      <a class="logo" href="/"<?php if ($brand['logoPrimary'] !== '') { ?> aria-label="<?= e($siteTitle) ?> – zur Startseite"<?php } ?>>
<?php if ($brand['logoPrimary'] !== '') { ?>
        <picture>
<?php if ($brand['logoIcon'] !== '') { ?>
          <source media="(max-width: 45.99rem)" srcset="<?= e($brand['logoIcon']) ?>" width="128" height="128">
<?php } ?>
          <img src="<?= e($brand['logoPrimary']) ?>" alt="" width="680" height="128">
        </picture>
<?php } else { ?>
        <span class="name"><?= e($siteTitle) ?></span>
<?php } ?>
      </a>
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
  <nav class="nav<?= $hatSeitenleiste ? ' nav-seitenleiste' : '' ?>" id="kat-menue" aria-label="Kategorien">
    <ul>
<?php foreach ($mainCategories as $index => $category) { ?>
      <li<?= $index >= 6 ? ' class="mehr"' : '' ?>><a href="<?= e($category['url']) ?>"<?php if ($category['slug'] === $activeCategory) { ?> aria-current="page"<?php } ?>><?= e($category['name']) ?><span class="zahl"><?= (int) $category['post_count'] ?></span></a></li>
<?php } ?>
      <li><a href="/kategorien/" class="alle">Alle Kategorien</a></li>
    </ul>
  </nav>
</header>

<div class="inhalt-huelle">
<?php if ($brand['contentBg'] !== '') { ?>
<div class="inhalt-grund" aria-hidden="true"><img src="<?= e($brand['contentBg']) ?>" alt="" width="2560" height="1212" decoding="async" fetchpriority="low"></div>
<?php } ?>
<main id="inhalt">
<?php if ($startseiteH1) { ?>
<h1 class="sr-nur"><?= e($siteTitle) ?></h1>
<?php } ?>
<?= $content ?>
</main>

<?php if ($brand['footerMascot'] !== '') { ?>
<div class="fuss-motiv"><img src="<?= e($brand['footerMascot']) ?>" alt="" width="960" height="480" loading="lazy" decoding="async"></div>
<?php } ?>
</div>
<footer class="fuss">
<?php if ($footTags !== [] || $footAbout !== '') { ?>
  <div class="wrap fuss-extra">
<?php if ($footAbout !== '') { ?>
    <section>
      <h2>Über <?= e($siteTitle) ?></h2>
      <p><?= e($footAbout) ?></p>
      <a class="weiter" href="/ueber/">Mehr über <?= e($siteTitle) ?> <span aria-hidden="true">→</span></a>
    </section>
<?php } ?>
<?php if ($footTags !== []) { ?>
    <section>
      <h2>Beliebte Schlagwörter</h2>
      <ul class="chips">
<?php foreach ($footTags as $tag) { ?>
        <li><a class="chip tag" href="<?= e($tag['url']) ?>"><?= e($tag['name']) ?> <span class="zahl"><?= (int) $tag['count'] ?></span></a></li>
<?php } ?>
      </ul>
    </section>
<?php } ?>
  </div>
<?php } ?>
  <div class="wrap">
    <a class="marke" href="/"><?php if ($brand['logoIcon'] !== '') { ?><img src="<?= e($brand['logoIcon']) ?>" alt="" width="128" height="128"><?php } ?><span><?= e($siteTitle) ?></span></a>
    <nav aria-label="Rechtliches und Kontakt"><a href="/ueber/">Kontakt</a><a href="/impressum/">Impressum</a><a href="/datenschutz/">Datenschutz</a></nav>
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
