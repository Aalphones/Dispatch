<?php declare(strict_types=1);

// Prüft den ContentRenderer und den Markdown-Umsetzer an den Demo-Artikeln (nur lokal):
// php bin/demo/pruefe-inhalt.php

use Dispatch\Db\Connection;
use Dispatch\Site\ContentRenderer;
use Dispatch\Site\SiteData;
use Dispatch\Util\Markdown;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__, 2);
$envFile = $root . '/app/.env.local';
if ((string) getenv('DISPATCH_ENV_FILE') === '' && is_file($envFile)) {
    putenv('DISPATCH_ENV_FILE=' . $envFile);
}
$config = require $root . '/app/bootstrap.php';

$failures = 0;
$check = static function (bool $isOk, string $label) use (&$failures): void {
    if (!$isOk) {
        $failures++;
        echo 'FEHLER: ', $label, PHP_EOL;
    }
};

$data = SiteData::load(Connection::open(), $config);
$renderer = new ContentRenderer();

// Der Demo-Artikel mit allen Diagrammformen (Datei, ohne Datenbank): jede Form gezeichnet, nur die erlaubten interaktiv.
$chartHtml = (string) file_get_contents(__DIR__ . '/energie-in-zahlen-diagramme.html');
$chartPost = ['slug' => 'energie-in-zahlen-diagramme', 'content_html' => $chartHtml, 'sources' => []];
$chartResult = $renderer->render($chartPost, $data);
$chartDocument = Dom\HTMLDocument::createFromString('<!doctype html><body>' . $chartResult['html'], LIBXML_NOERROR, 'UTF-8');
$expectedTypes = ['balken', 'saeulen', 'linie', 'flaeche', 'balken-gestapelt', 'saeulen-gestapelt', 'kreis', 'ring', 'netz', 'waffel', 'wasserfall', 'heatmap', 'kalender', 'sankey', 'baumkarte', 'blasen', 'karte'];
foreach ($expectedTypes as $type) {
    $check($chartDocument->querySelectorAll('figure.diagramm[data-typ="' . $type . '"]')->length >= 1, 'Demo-Diagramme: Typ fehlt: ' . $type);
}
$interactiveTypes = ['linie', 'heatmap', 'sankey', 'baumkarte', 'blasen', 'karte'];
foreach ($chartDocument->querySelectorAll('figure.diagramm[data-interaktiv]') as $figure) {
    $check(in_array($figure->getAttribute('data-typ'), $interactiveTypes, true), 'Demo-Diagramme: unerwartet interaktiv: ' . $figure->getAttribute('data-typ'));
}
$check($chartResult['diagramme'] && $chartResult['diagramme_interaktiv'], 'Demo-Diagramme: Flags diagramme/diagramme_interaktiv fehlen');
$check($chartDocument->querySelectorAll('figure.chart')->length === 0, 'Demo-Diagramme: figure.chart übrig (nicht gezeichnet)');
echo sprintf('%-45s diagramme %d · interaktiv %d', 'energie-in-zahlen-diagramme', $chartDocument->querySelectorAll('figure.diagramm')->length, $chartDocument->querySelectorAll('figure.diagramm[data-interaktiv]')->length), PHP_EOL;

foreach ($data->posts as $post) {
    $result = $renderer->render($post, $data);
    $document = Dom\HTMLDocument::createFromString('<!doctype html><body>' . $result['html'], LIBXML_NOERROR, 'UTF-8');
    $count = static fn (string $selector): int => $document->querySelectorAll($selector)->length;

    echo sprintf(
        '%-45s abb %d · galerie %d · einbettung %d · beleg %d · h2[id] %d · toc %d · text %d Zeichen',
        $post['slug'],
        $count('figure.abb'),
        $count('figure.galerie'),
        $count('figure.einbettung'),
        $count('sup.beleg'),
        $count('h2[id]'),
        count($result['toc']),
        mb_strlen($result['text'])
    ), PHP_EOL;

    $check($count('[data-media]') === 0, $post['slug'] . ': data-media übrig');
    $check($count('[data-cite]') === 0, $post['slug'] . ': data-cite übrig');
    $check($count('iframe, script') === 0, $post['slug'] . ': iframe oder script im Ergebnis');
    $check($count('h2') === $count('h2[id]'), $post['slug'] . ': h2 ohne id');
    $check(count($result['toc']) === $count('h2'), $post['slug'] . ': Inhaltsverzeichnis stimmt nicht mit den h2 überein');
    $check($result['text'] !== '', $post['slug'] . ': leerer Klartext');
}

// Markdown: Impressum-Startinhalt ohne verlorenen Text, XSS-Fälle aus AK 10.
$imprint = (string) file_get_contents($root . '/app/defaults/imprint.md');
$imprintHtml = Markdown::toHtml($imprint);
$imprintText = html_entity_decode(strip_tags($imprintHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
foreach (['Angaben nach § 5 DDG', '[[Vor- und Nachname]]', '[[Straße und Hausnummer]]', '[[E-Mail-Adresse]]', 'Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV'] as $needle) {
    $check(str_contains($imprintText, $needle), 'Impressum: Text verloren: ' . $needle);
}
$check(str_starts_with($imprintHtml, '<h2>Impressum</h2>'), 'Impressum: # wird nicht zu h2');

$script = Markdown::toHtml('<script>alert(1)</script>');
$check(!str_contains($script, '<script') && str_contains($script, '&lt;script&gt;'), 'Markdown: script nicht maskiert: ' . $script);

$javascriptLink = Markdown::toHtml('[x](javascript:alert(1))');
$check(!str_contains($javascriptLink, '<a') && !str_contains($javascriptLink, 'href'), 'Markdown: javascript-Link bleibt: ' . $javascriptLink);

$obfuscated = Markdown::toHtml("[x](JaVa\tScRiPt:alert(1)) [y](//evil.example/) [z](data:text/html,x)");
$check(!str_contains($obfuscated, '<a') && !str_contains($obfuscated, 'href'), 'Markdown: verschleierter Link bleibt: ' . $obfuscated);

$attribute = Markdown::toHtml('[x](https://example.org/"onmouseover="alert(1))');
$check(!str_contains($attribute, 'onmouseover=') || str_contains($attribute, '&quot;onmouseover'), 'Markdown: Attributausbruch: ' . $attribute);

$image = Markdown::toHtml('![a](x" onerror="alert(1)) <img src=x onerror=alert(1)>');
$check(!str_contains($image, '<img'), 'Markdown: img im Ergebnis: ' . $image);

$allowed = Markdown::toHtml("[a](https://example.org/p) [b](/intern/) [c](mailto:a@example.org)\n\n- eins\n- **zwei**\n\n1. *drei*\n\n> Zitat\n\n---");
foreach (['<a href="https://example.org/p">a</a>', '<a href="/intern/">b</a>', '<a href="mailto:a@example.org">c</a>', '<ul><li>eins</li><li><strong>zwei</strong></li></ul>', '<ol><li><em>drei</em></li></ol>', '<blockquote><p>Zitat</p></blockquote>', '<hr>'] as $needle) {
    $check(str_contains($allowed, $needle), 'Markdown: erwartet ' . $needle . ' in ' . $allowed);
}

echo $failures === 0 ? 'Alles in Ordnung.' : $failures . ' Fehler.', PHP_EOL;
exit($failures === 0 ? 0 : 1);
