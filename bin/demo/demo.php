<?php declare(strict_types=1);

// Demo-Bestand für `php bin/dispatch demo` (nur lokal). Die Quellen sind erfundene Beispiele auf example.*-Adressen.

/**
 * Ein Farbverlauf als JPEG mit der Aufschrift „DISPATCH Demo {nr}“ — ohne Netz, nur mit GD.
 *
 * @param string $farbe Startfarbe als „#rrggbb“; die Endfarbe leitet sich aus der Nummer ab
 */
function demoBild(int $nr, string $farbe, bool $hochformat = false): string
{
    $breite = $hochformat ? 1000 : 1600;
    $hoehe = $hochformat ? 1250 : 1000;

    $start = sscanf($farbe, '#%02x%02x%02x');
    $ende = [
        (int) (($start[2] + 90 + $nr * 7) % 256),
        (int) (($start[0] + 60 + $nr * 5) % 256),
        (int) (($start[1] + 120 + $nr * 3) % 256),
    ];

    // Der Verlauf entsteht klein und wird geglättet vergrößert; das ist schnell und ohne Streifen.
    $klein = imagecreatetruecolor(64, 40);
    for ($y = 0; $y < 40; $y++) {
        for ($x = 0; $x < 64; $x++) {
            $anteil = ($x / 64 + $y / 40) / 2;
            $rot = (int) ($start[0] + ($ende[0] - $start[0]) * $anteil);
            $gruen = (int) ($start[1] + ($ende[1] - $start[1]) * $anteil);
            $blau = (int) ($start[2] + ($ende[2] - $start[2]) * $anteil);
            imagesetpixel($klein, $x, $y, imagecolorallocate($klein, $rot, $gruen, $blau));
        }
    }
    $bild = imagecreatetruecolor($breite, $hoehe);
    imagecopyresampled($bild, $klein, 0, 0, 0, 0, $breite, $hoehe, 64, 40);

    $beschriftung = 'DISPATCH Demo ' . $nr;
    $textBreite = imagefontwidth(5) * strlen($beschriftung);
    $textHoehe = imagefontheight(5);
    $text = imagecreatetruecolor($textBreite, $textHoehe);
    imagefill($text, 0, 0, imagecolorallocate($text, 0, 0, 0));
    imagestring($text, 5, 0, 0, $beschriftung, imagecolorallocate($text, 255, 255, 255));
    $zoom = 4;
    $vergroessert = imagecreatetruecolor($textBreite * $zoom, $textHoehe * $zoom);
    imagecopyresized($vergroessert, $text, 0, 0, 0, 0, $textBreite * $zoom, $textHoehe * $zoom, $textBreite, $textHoehe);
    imagecopymerge(
        $bild,
        $vergroessert,
        (int) (($breite - $textBreite * $zoom) / 2),
        (int) (($hoehe - $textHoehe * $zoom) / 2),
        0,
        0,
        $textBreite * $zoom,
        $textHoehe * $zoom,
        70
    );

    ob_start();
    imagejpeg($bild, null, 88);

    return (string) ob_get_clean();
}

/** @return array<string, string> eine Quelle im Format des Artikel-Felds `sources` */
function demoQuelle(string $ref, string $art, string $titel, string $adresse, string $herausgeber, string $erschienen): array
{
    return [
        'ref' => $ref,
        'kind' => $art,
        'title' => $titel,
        'url' => $adresse,
        'publisher' => $herausgeber,
        'published' => $erschienen,
        'accessed' => '2026-11-09',
    ];
}

return [
    'categories' => [
        ['slug' => 'wissenschaft', 'name' => 'Wissenschaft', 'description' => 'Neue Erkenntnisse aus Forschung und Labor, verständlich eingeordnet.', 'position' => 10],
        ['slug' => 'klima', 'name' => 'Klima', 'description' => 'Messdaten, Modelle und Folgen des Klimawandels.', 'position' => 1, 'parent' => 'wissenschaft'],
        ['slug' => 'raumfahrt', 'name' => 'Raumfahrt', 'description' => 'Missionen, Technik und Streitfragen rund um den Weltraum.', 'position' => 2, 'parent' => 'wissenschaft'],
        ['slug' => 'technik', 'name' => 'Technik', 'description' => 'Digitale Werkzeuge und ihre Wirkung auf den Alltag.', 'position' => 20],
        ['slug' => 'ki', 'name' => 'Künstliche Intelligenz', 'description' => 'Was Sprachmodelle und lernende Systeme heute leisten und wo sie scheitern.', 'position' => 1, 'parent' => 'technik'],
        ['slug' => 'sicherheit', 'name' => 'Sicherheit', 'description' => 'Schutz von Daten, Konten und Geräten im Alltag.', 'position' => 2, 'parent' => 'technik'],
        ['slug' => 'gesellschaft', 'name' => 'Gesellschaft', 'description' => 'Wie Menschen zusammenleben, sich bewegen und miteinander umgehen.', 'position' => 30],
        ['slug' => 'wirtschaft', 'name' => 'Wirtschaft', 'description' => 'Märkte, Betriebe und die Frage, wovon wir leben.', 'position' => 40],
        ['slug' => 'energie', 'name' => 'Energie', 'description' => 'Strom, Wärme und Speicher auf dem Weg zur Klimaneutralität.', 'position' => 1, 'parent' => 'wirtschaft'],
        ['slug' => 'arbeit', 'name' => 'Arbeit', 'description' => 'Neue Arbeitsmodelle und ihre Folgen für Betriebe und Beschäftigte.', 'position' => 2, 'parent' => 'wirtschaft'],
        ['slug' => 'kultur', 'name' => 'Kultur', 'description' => 'Orte, Medien und Ideen, die das Zusammenleben prägen.', 'position' => 50],
    ],
    // Reihenfolge = Alter: der erste Eintrag ist der neueste Artikel.
    'posts' => [
        [
            'file' => 'energie-in-zahlen-diagramme',
            'title' => 'Energie in Zahlen: Alle Diagrammformen im Überblick',
            'subtitle' => 'Ein Beispielartikel mit erfundenen Daten, an dem sich jede Form ausprobieren lässt',
            'kicker' => 'Energie',
            'excerpt' => 'Siebzehn Diagrammformen an einem Thema: vom schlichten Balken bis zur anklickbaren Karte. Alle Zahlen sind erfunden.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Energie', 'Daten', 'Klima'],
            'categories' => [['slug' => 'energie', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'dataset', 'Beispielstatistik zur Stromerzeugung', 'https://daten.example.net/energie/stromerzeugung', 'Beispiel-Energieinstitut', '2026-02'),
                demoQuelle('s2', 'official', 'Netzbericht mit Speicherkapazitäten', 'https://www.example.org/energie/netzbericht', 'Beispiel-Netzagentur', '2026-05'),
                demoQuelle('s3', 'study', 'Vergleich von Speichertechniken', 'https://www.example.com/studien/speichervergleich', 'Journal für Energiesysteme', '2025-11'),
            ],
        ],
        [
            'file' => 'raumsonde-eismond-bohrung',
            'title' => 'Wie eine Sonde unter das Eis des Jupitermondes Europa kommen soll',
            'subtitle' => 'Zwei Meter Schmelzsonde, ein Jahrzehnt Flugzeit und viele offene Fragen',
            'kicker' => 'Raumfahrt',
            'excerpt' => 'Eine geplante Mission will durch die Eiskruste des Mondes Europa dringen. Die Technik dafür gibt es erst auf dem Papier.',
            'layout' => 'feature',
            'accent' => null,
            'tags' => ['Raumfahrt', 'Forschung', 'Daten'],
            'categories' => [['slug' => 'raumfahrt', 'primary' => true]],
            'bilder' => [false, true],
            'sources' => [
                demoQuelle('s1', 'official', 'Studie zur Schmelzsonde für eisbedeckte Monde', 'https://www.example.org/raumfahrt/schmelzsonde', 'Beispiel-Raumfahrtinstitut', '2026-03'),
                demoQuelle('s2', 'study', 'Dicke und Struktur der Eiskruste von Europa', 'https://www.example.com/forschung/eiskruste', 'Journal für Planetenforschung', '2025-11-14'),
                demoQuelle('s3', 'news', 'Mission zum Jupitersystem: Zeitplan und Kosten', 'https://nachrichten.example.net/raumfahrt/jupiter-zeitplan', 'Beispiel-Nachrichten', '2026-06-02'),
                demoQuelle('s4', 'interview', 'Gespräch mit einer Ingenieurin des Sondenteams', '', 'Beispiel-Wissenschaftspodcast', '2026-08'),
            ],
        ],
        [
            'file' => 'ki-sprachmodelle-nachpruefen',
            'title' => 'Der Prüfstand für Sprachmodelle: Wie sich Antworten nachprüfen lassen',
            'subtitle' => 'Ein Rundgang durch alle Bausteine, die dieses Magazin für lange Artikel bereithält',
            'kicker' => 'Künstliche Intelligenz',
            'excerpt' => 'Sprachmodelle antworten flüssig, aber nicht immer richtig. Wer Belege verlangt, erkennt schneller, wo ein Text trägt.',
            'layout' => 'longform',
            'accent' => null,
            'tags' => ['KI', 'Daten', 'Forschung', 'Bildung', 'Sicherheit'],
            'categories' => [['slug' => 'ki', 'primary' => true], ['slug' => 'wissenschaft', 'primary' => false]],
            // 1 inline, 2 wide, 3 full, 4 left, 5 right, 6–8 Galerie
            'bilder' => [false, false, false, true, true, false, false, false],
            'sources' => [
                demoQuelle('s1', 'study', 'Wie zuverlässig sind Antworten großer Sprachmodelle?', 'https://www.example.org/ki/zuverlaessigkeit', 'Beispiel-Forschungsgemeinschaft', '2026-02'),
                demoQuelle('s2', 'dataset', 'Prüfsatz für Faktenfragen in deutscher Sprache', 'https://daten.example.net/pruefsatz-fakten', 'Beispiel-Datenarchiv', '2025-12-01'),
                demoQuelle('s3', 'news', 'Hochschulen testen KI-Assistenten im Studium', 'https://www.example.com/bildung/ki-studium', 'Beispiel-Bildungsmagazin', '2026-05-20'),
                demoQuelle('s4', 'book', 'Maschinen, die sprechen: Eine Einführung', '', 'Beispiel-Verlag', '2025'),
                demoQuelle('s5', 'official', 'Leitfaden zum Umgang mit KI-Texten in Behörden', 'https://www.example.org/leitfaden/ki-texte', 'Beispiel-Digitalministerium', '2026-04-10'),
                demoQuelle('s6', 'video', 'Vortrag: Prüfen statt glauben', 'https://video.example.net/vortrag-pruefen', 'Beispiel-Konferenz', '2026-01-18'),
            ],
        ],
        [
            'file' => 'passkeys-statt-passwoerter',
            'title' => 'Passkeys statt Passwörter: Was sich beim Anmelden ändert',
            'subtitle' => null,
            'kicker' => 'Sicherheit',
            'excerpt' => 'Immer mehr Dienste lassen die Anmeldung per Passkey zu. Das ist bequemer und schützt besser vor gefälschten Seiten.',
            'layout' => 'briefing',
            'accent' => null,
            'tags' => ['Sicherheit', 'Datenschutz'],
            'categories' => [['slug' => 'sicherheit', 'primary' => true]],
            'bilder' => [],
            'sources' => [
                demoQuelle('s1', 'official', 'Empfehlung zur passwortlosen Anmeldung', 'https://www.example.org/sicherheit/passkeys', 'Beispiel-Sicherheitsamt', '2026-01'),
                demoQuelle('s2', 'study', 'Wirksamkeit von Passkeys gegen Phishing', 'https://www.example.com/studien/passkeys-phishing', 'Journal für Informationssicherheit', '2025-09-30'),
                demoQuelle('s3', 'news', 'Große Anbieter schalten Passkeys frei', 'https://nachrichten.example.net/digital/passkeys', 'Beispiel-Nachrichten', '2026-03-12'),
            ],
        ],
        [
            'file' => 'gletscherschmelze-alpen-messnetz',
            'title' => 'Alpengletscher verlieren schneller Eis, als Modelle vorhersagen',
            'subtitle' => 'Ein dichteres Messnetz zeigt, wo die Prognosen zu vorsichtig waren',
            'kicker' => 'Klima',
            'excerpt' => 'Neue Messungen an Pegeln und Bohrlöchern zeigen: Mehrere Alpengletscher schrumpfen deutlich rascher als berechnet.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Klima', 'Forschung', 'Daten'],
            'categories' => [['slug' => 'klima', 'primary' => true]],
            'bilder' => [false, false, false, false, false, false, false, false, false],
            'sources' => [
                demoQuelle('s1', 'dataset', 'Massenbilanz ausgewählter Alpengletscher 1960 bis 2025', 'https://daten.example.net/gletscher/massenbilanz', 'Beispiel-Gletscherdienst', '2026-01-15'),
                demoQuelle('s2', 'study', 'Vergleich von Gletschermodellen mit Feldmessungen', 'https://www.example.org/klima/gletschermodelle', 'Journal für Glaziologie', '2025-10'),
                demoQuelle('s3', 'news', 'Skigebiete rechnen mit weniger Eis', 'https://www.example.com/regional/skigebiete-eis', 'Beispiel-Regionalzeitung', '2026-02-27'),
            ],
        ],
        [
            'file' => 'stromnetz-speicher-winter',
            'title' => 'Wie Speicher das Stromnetz durch den Winter tragen',
            'subtitle' => 'Batterien, Pumpspeicher und Wasserstoff im Vergleich',
            'kicker' => 'Energie',
            'excerpt' => 'Im Winter liefert die Sonne wenig, und der Wind bleibt manchmal tagelang aus. Welche Speicher diese Lücke schließen können.',
            'layout' => 'feature',
            'accent' => '#c2410c',
            'tags' => ['Energie', 'Stadt', 'Klima'],
            'categories' => [['slug' => 'energie', 'primary' => true], ['slug' => 'klima', 'primary' => false]],
            'bilder' => [false, false],
            'sources' => [
                demoQuelle('s1', 'official', 'Bericht zur Versorgungssicherheit im Winter', 'https://www.example.org/energie/versorgung-winter', 'Beispiel-Netzagentur', '2026-09'),
                demoQuelle('s2', 'study', 'Speicherbedarf bei hohem Anteil erneuerbarer Energie', 'https://www.example.com/studien/speicherbedarf', 'Journal für Energiesysteme', '2025-12'),
                demoQuelle('s3', 'dataset', 'Einspeisung und Verbrauch im Stundenraster', 'https://daten.example.net/strom/stundenwerte', 'Beispiel-Energiedatenportal', '2026-10-01'),
                demoQuelle('s4', 'news', 'Großbatterie geht in einer Kleinstadt ans Netz', 'https://nachrichten.example.net/energie/grossbatterie', 'Beispiel-Nachrichten', '2026-07-08'),
            ],
        ],
        [
            'file' => 'ki-assistenten-im-klassenzimmer',
            'title' => 'KI-Assistenten im Klassenzimmer: Was Lehrkräfte berichten',
            'subtitle' => null,
            'kicker' => 'Künstliche Intelligenz',
            'excerpt' => 'Schulen probieren Schreibassistenten aus. Erste Erfahrungen zeigen Chancen bei Feedback und Grenzen beim Lernen.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['KI', 'Bildung', 'Datenschutz'],
            'categories' => [['slug' => 'ki', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'study', 'Schreibassistenten im Unterricht: Eine Befragung', 'https://www.example.org/bildung/schreibassistenten', 'Beispiel-Bildungsinstitut', '2026-06'),
                demoQuelle('s2', 'official', 'Handreichung zu KI und Datenschutz an Schulen', 'https://www.example.com/schule/handreichung-ki', 'Beispiel-Schulbehörde', '2026-03-05'),
                demoQuelle('s3', 'interview', 'Gespräch mit einer Gymnasiallehrerin', '', 'Beispiel-Bildungspodcast', '2026-09'),
            ],
        ],
        [
            'file' => 'ozeanstroemung-schwaecht-sich',
            'title' => 'Die große Atlantikströmung schwächt sich ab, aber nicht überall gleich',
            'subtitle' => 'Messketten, Modelle und die Frage, wie nah der Kipppunkt wirklich ist',
            'kicker' => 'Klima',
            'excerpt' => 'Eine Messkette im Atlantik zeigt, wie viel Wärme die Strömung noch nach Norden trägt. Die Modelle liegen weit auseinander.',
            'layout' => 'longform',
            'accent' => '#0b7a75',
            'tags' => ['Klima', 'Ozean', 'Forschung', 'Daten'],
            'categories' => [['slug' => 'klima', 'primary' => true]],
            'bilder' => [false, false],
            'sources' => [
                demoQuelle('s1', 'dataset', 'Messreihe der Atlantikströmung bei 26,5 Grad Nord', 'https://daten.example.net/ozean/messkette', 'Beispiel-Ozeandatenzentrum', '2026-04'),
                demoQuelle('s2', 'study', 'Streuung der Modelle bei der Atlantikzirkulation', 'https://www.example.org/klima/atlantikzirkulation', 'Journal für Klimaforschung', '2025-08'),
                demoQuelle('s3', 'study', 'Frühwarnsignale für Kipppunkte im Ozean', 'https://www.example.com/studien/fruehwarnsignale', 'Journal für Erdsystemforschung', '2026-01-22'),
                demoQuelle('s4', 'interview', 'Gespräch mit einer Ozeanografin', '', 'Beispiel-Wissenschaftspodcast', '2026-05'),
                demoQuelle('s5', 'news', 'Forschungsschiff wartet die Messkette', 'https://nachrichten.example.net/forschung/messkette-wartung', 'Beispiel-Nachrichten', '2026-04-18'),
            ],
        ],
        [
            'file' => 'rad-schnellwege-stadtplanung',
            'title' => 'Rad-Schnellwege: Warum Städte jetzt in lange Strecken investieren',
            'subtitle' => 'Breite Trassen, Vorfahrt und ein Netz, das Dörfer mit der Innenstadt verbindet',
            'kicker' => 'Gesellschaft',
            'excerpt' => 'Wer täglich zehn Kilometer fährt, braucht mehr als einen Streifen am Straßenrand. Wie Radschnellwege Pendeln verändern.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Mobilität', 'Stadt', 'Klima'],
            'categories' => [['slug' => 'gesellschaft', 'primary' => true]],
            'bilder' => [false, false],
            'sources' => [
                demoQuelle('s1', 'study', 'Pendeln mit dem Rad auf Strecken über fünf Kilometer', 'https://www.example.org/verkehr/pendeln-rad', 'Beispiel-Verkehrsforschung', '2025-11'),
                demoQuelle('s2', 'official', 'Förderprogramm für Radschnellverbindungen', 'https://www.example.com/foerderung/radschnellwege', 'Beispiel-Verkehrsministerium', '2026-02-12'),
                demoQuelle('s3', 'news', 'Erste Teilstücke eröffnet', 'https://nachrichten.example.net/stadt/radschnellweg-eroeffnet', 'Beispiel-Nachrichten', '2026-07-30'),
            ],
        ],
        [
            'file' => 'phishing-welle-kleine-betriebe',
            'title' => 'Phishing-Welle trifft kleine Betriebe: So erkennen Sie gefälschte Rechnungen',
            'subtitle' => null,
            'kicker' => 'Sicherheit',
            'excerpt' => 'Gefälschte Rechnungen und Chef-Mails treffen vor allem kleine Firmen. Vier Merkmale helfen, sie früh zu erkennen.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Sicherheit', 'Datenschutz', 'Arbeit'],
            'categories' => [['slug' => 'sicherheit', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'official', 'Lagebericht zur Cyberkriminalität', 'https://www.example.org/sicherheit/lagebericht', 'Beispiel-Kriminalamt', '2026-05'),
                demoQuelle('s2', 'news', 'Handwerksbetrieb verliert fünfstelligen Betrag', 'https://nachrichten.example.net/wirtschaft/fake-rechnung', 'Beispiel-Nachrichten', '2026-08-14'),
                demoQuelle('s3', 'study', 'Wirksamkeit von Schulungen gegen Phishing', 'https://www.example.com/studien/phishing-schulung', 'Journal für Informationssicherheit', '2025-10'),
            ],
        ],
        [
            'file' => 'vier-tage-woche-bilanz',
            'title' => 'Vier-Tage-Woche: Die Zwischenbilanz nach einem Jahr',
            'subtitle' => null,
            'kicker' => 'Arbeit',
            'excerpt' => 'Zwanzig Betriebe haben ein Jahr lang weniger gearbeitet. Krankenstand und Zufriedenheit sinken und steigen anders als gedacht.',
            'layout' => 'briefing',
            'accent' => null,
            'tags' => ['Arbeit', 'Bildung'],
            'categories' => [['slug' => 'arbeit', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'study', 'Vier-Tage-Woche in Betrieben: Ergebnisse nach zwölf Monaten', 'https://www.example.org/arbeit/vier-tage-woche', 'Beispiel-Arbeitsmarktinstitut', '2026-09'),
                demoQuelle('s2', 'news', 'Erfahrungen aus einem Maschinenbauer', 'https://nachrichten.example.net/arbeit/maschinenbau-vier-tage', 'Beispiel-Nachrichten', '2026-06-18'),
                demoQuelle('s3', 'interview', 'Gespräch mit einer Personalleiterin', '', 'Beispiel-Wirtschaftspodcast', '2026-10'),
            ],
        ],
        [
            'file' => 'waermepumpen-altbau-bilanz',
            'title' => 'Wärmepumpen im Altbau: Was Messungen nach zwei Wintern zeigen',
            'subtitle' => 'Verbrauch, Kosten und Lärm in 120 Häusern',
            'kicker' => 'Energie',
            'excerpt' => 'Funktioniert eine Wärmepumpe auch in unsanierten Häusern? Zwei Winter Messdaten geben eine überraschend klare Antwort.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Energie', 'Stadt', 'Klima'],
            'categories' => [['slug' => 'energie', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'dataset', 'Feldtest Wärmepumpen im Gebäudebestand', 'https://daten.example.net/waerme/feldtest', 'Beispiel-Energieinstitut', '2026-06'),
                demoQuelle('s2', 'official', 'Förderbedingungen für Wärmepumpen', 'https://www.example.org/foerderung/waermepumpen', 'Beispiel-Wirtschaftsministerium', '2026-01'),
                demoQuelle('s3', 'news', 'Handwerker suchen Nachwuchs für die Wärmewende', 'https://www.example.com/handwerk/waermewende', 'Beispiel-Handwerksblatt', '2026-04-03'),
            ],
        ],
        [
            'file' => 'satelliten-schwarm-orbit-muell',
            'title' => 'Tausende Satelliten, wenig Regeln: Wer räumt im Orbit auf?',
            'subtitle' => 'Warum Weltraumschrott zum Problem für alle wird',
            'kicker' => 'Raumfahrt',
            'excerpt' => 'Große Satellitenschwärme füllen die erdnahen Bahnen. Fachleute fordern verbindliche Regeln für Betrieb und Entsorgung.',
            'layout' => 'standard',
            'accent' => '#7c3aed',
            'tags' => ['Raumfahrt', 'Sicherheit', 'Daten'],
            'categories' => [['slug' => 'raumfahrt', 'primary' => true], ['slug' => 'sicherheit', 'primary' => false]],
            'bilder' => [false, true],
            'sources' => [
                demoQuelle('s1', 'dataset', 'Katalog der Objekte in erdnahen Umlaufbahnen', 'https://daten.example.net/orbit/katalog', 'Beispiel-Weltraumbeobachtung', '2026-10'),
                demoQuelle('s2', 'official', 'Leitlinien zur Vermeidung von Weltraumschrott', 'https://www.example.org/raumfahrt/leitlinien-schrott', 'Beispiel-Raumfahrtagentur', '2025-06'),
                demoQuelle('s3', 'news', 'Ausweichmanöver häufen sich', 'https://nachrichten.example.net/raumfahrt/ausweichmanoever', 'Beispiel-Nachrichten', '2026-08-21'),
                demoQuelle('s4', 'study', 'Kollisionsrisiko bei dichten Satellitenschwärmen', 'https://www.example.com/studien/kollisionsrisiko', 'Journal für Raumfahrtsysteme', '2026-02'),
            ],
        ],
        [
            'file' => 'homeoffice-pendler-effekt',
            'title' => 'Homeoffice verändert, wohin Menschen ziehen',
            'subtitle' => 'Wer nur zwei Tage pendelt, nimmt längere Wege in Kauf',
            'kicker' => 'Arbeit',
            'excerpt' => 'Mit festen Homeoffice-Tagen wächst der Radius, in dem Beschäftigte wohnen. Das verschiebt Mieten, Verkehr und Gemeinden.',
            'layout' => 'standard',
            'accent' => null,
            'tags' => ['Arbeit', 'Mobilität', 'Stadt'],
            'categories' => [['slug' => 'arbeit', 'primary' => true]],
            'bilder' => [false],
            'sources' => [
                demoQuelle('s1', 'study', 'Wohnortwahl bei hybrider Arbeit', 'https://www.example.org/arbeit/wohnortwahl', 'Beispiel-Raumforschung', '2026-03'),
                demoQuelle('s2', 'dataset', 'Pendlerströme nach Gemeinden', 'https://daten.example.net/arbeit/pendlerstroeme', 'Beispiel-Statistikamt', '2026-09'),
                demoQuelle('s3', 'news', 'Mieten im Umland steigen schneller als in der Stadt', 'https://www.example.com/wohnen/umland-mieten', 'Beispiel-Immobilienmagazin', '2026-05-26'),
            ],
        ],
        [
            'file' => 'stadtbibliothek-neu-gedacht',
            'title' => 'Die Stadtbibliothek als dritter Ort: Was Besucher dort suchen',
            'subtitle' => 'Zwischen Werkstatt, Café und Lesesaal',
            'kicker' => 'Kultur',
            'excerpt' => 'Bibliotheken verleihen längst mehr als Bücher. Sie bieten Werkstätten, Arbeitsplätze und Raum für Begegnung ohne Konsumzwang.',
            'layout' => 'standard',
            'accent' => '#be185d',
            'tags' => ['Kultur', 'Bildung', 'Stadt'],
            'categories' => [['slug' => 'kultur', 'primary' => true]],
            'bilder' => [false, false],
            'sources' => [
                demoQuelle('s1', 'study', 'Besuchsmotive in öffentlichen Bibliotheken', 'https://www.example.org/kultur/besuchsmotive', 'Beispiel-Bibliotheksverband', '2026-04'),
                demoQuelle('s2', 'news', 'Eine Stadt baut ihre Zentralbibliothek um', 'https://nachrichten.example.net/kultur/zentralbibliothek', 'Beispiel-Nachrichten', '2026-07-11'),
                demoQuelle('s3', 'book', 'Der dritte Ort: Öffentliche Räume neu denken', '', 'Beispiel-Verlag', '2024'),
            ],
        ],
    ],
];
