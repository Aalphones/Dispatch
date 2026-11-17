<?php

declare(strict_types=1);

/**
 * Ziel-Struktur der Kategorien (ADR-037): sieben Hauptkategorien mit Unterkategorien, die Zuordnung der bestehenden
 * Artikel und offenen Queue-Vorschläge und die Abkündigung der alten Kategorien.
 *
 * Gelesen von `Domain/CategoryRestructureService`. Ein Slug, den es schon gibt, wird umgehängt statt neu angelegt.
 */

return [
    'tree' => [
        'disney' => [
            'name' => 'Disney', 'interest' => 5,
            'description' => 'Parks, Unternehmensgeschichte, Filme, Animation und TV, Figuren, Imagineering, Kreuzfahrtschiffe, Resorts, Musik, Spiele und Studios.',
            'keywords' => ['Disney Parks', 'Imagineering', 'Disney Animation', 'Disney Cruise Line', 'Disney Studios'],
            'children' => [
                'disney-parks-resorts' => ['name' => 'Parks & Resorts', 'description' => 'Disneyland, Walt Disney World, Disneyland Paris, Hotels, Attraktionen und Easter Eggs.', 'keywords' => ['Disneyland Paris', 'Walt Disney World', 'Attraktionen', 'Hotels']],
                'disney-filme-animation' => ['name' => 'Filme & Animation', 'description' => 'Zeichentrick, Pixar, Realfilme, Serien und Figuren der Disney-Studios.', 'keywords' => ['Pixar', 'Zeichentrick', 'Disney+']],
                'disney-imagineering-technik' => ['name' => 'Imagineering & Technik', 'description' => 'Wie Attraktionen entstehen: Animatronics, Fahrgeschäfte, Effekte.', 'keywords' => ['Imagineering', 'Animatronics']],
                'disney-geschichte-unternehmen' => ['name' => 'Disney-Geschichte & Unternehmen', 'description' => 'Walt Disney, Konzerngeschichte, Übernahmen und Geschäftszahlen.', 'keywords' => ['Walt Disney', 'Konzern']],
                'disney-kreuzfahrten' => ['name' => 'Disney-Kreuzfahrten', 'description' => 'Disney Cruise Line: Schiffe, Routen, Inseln und Bordprogramm.', 'keywords' => ['Disney Cruise Line', 'Kreuzfahrt']],
                'disney-musik-buehne' => ['name' => 'Disney-Musik & Bühnenproduktionen', 'description' => 'Soundtracks, Songs, Disney-Musicals und ihre Darsteller.', 'keywords' => ['Musical', 'Soundtrack', 'Disney Musical']],
            ],
        ],
        'film-serien' => [
            'name' => 'Film & Serien', 'interest' => 4,
            'description' => 'Marvel, DC, Star Wars, Wizarding World, Fantasy, Anime sowie Streaming-Produktionen, Casting und Hintergründe.',
            'keywords' => ['Film', 'Serie', 'Netflix', 'Prime Video'],
            'children' => [
                'marvel-dc' => ['name' => 'Marvel & DC', 'description' => 'MCU, DCU und Comic-Verfilmungen.', 'keywords' => ['Marvel', 'MCU', 'DC Comics']],
                'star-wars' => ['name' => 'Star Wars', 'description' => 'Filme, Serien, Figuren und Hintergründe.', 'keywords' => ['Star Wars']],
                'wizarding-world' => ['name' => 'Wizarding World', 'description' => 'Harry Potter, Phantastische Tierwesen und die Serien.', 'keywords' => ['Harry Potter']],
                'fantasy-science-fiction' => ['name' => 'Fantasy & Science-Fiction', 'description' => 'Herr der Ringe, Die Ringe der Macht, The Witcher und andere Fantasy- und SF-Welten.', 'keywords' => ['Herr der Ringe', 'The Witcher', 'Avatar: The Last Airbender']],
                'anime-manga' => ['name' => 'Anime & Manga', 'description' => 'One Piece, Naruto, Ranma 1/2 und mehr.', 'keywords' => ['One Piece', 'Naruto', 'Ranma 1/2']],
                'streaming-produktionen' => ['name' => 'Streaming & Produktionen', 'description' => 'Netflix, Prime Video, Produktion und Streaminggeschichte.', 'keywords' => ['Netflix', 'Amazon Prime Video']],
                'schauspieler-besetzung' => ['name' => 'Schauspieler & Besetzung', 'description' => 'Casting, Darsteller und ihre Rollen in Filmen und Serien.', 'keywords' => ['Casting', 'Besetzung']],
            ],
        ],
        'gaming-sammelkarten' => [
            'name' => 'Gaming & Sammelkarten', 'interest' => 3,
            'description' => 'Pokémon, Disney Lorcana, World of Warcraft, Videospiele, die Gaming-Branche und alles rund ums Sammeln.',
            'keywords' => ['Pokémon', 'World of Warcraft', 'Lorcana', 'Pokemon TCG'],
            'children' => [
                'pokemon' => ['name' => 'Pokémon', 'description' => 'Pokémon TCG, Decks, Sets und Spiele.', 'keywords' => ['Pokémon', 'Pokemon TCG']],
                'disney-lorcana' => ['name' => 'Disney Lorcana', 'description' => 'Sets, Decks und Karten von Disney Lorcana.', 'keywords' => ['Lorcana']],
                'world-of-warcraft' => ['name' => 'World of Warcraft', 'description' => 'WoW, Classic, Add-ons und Erweiterungen.', 'keywords' => ['World of Warcraft', 'WoW Forever']],
                'videospiele-konsolen' => ['name' => 'Videospiele & Konsolen', 'description' => 'Einzelne Spiele, Reihen, Konsolen und Plattformen.', 'keywords' => ['GTA', 'Zelda', 'Konsolen']],
                'gaming-branche-geschaeftsmodelle' => ['name' => 'Gaming-Branche & Geschäftsmodelle', 'description' => 'Plattformen, Preise, Lizenzen, Spieleökonomie und Abschaltungen.', 'keywords' => ['Steam', 'Plattformen', 'Spielökonomie']],
                'sammeln-karten-grading' => ['name' => 'Sammeln, Karten & Grading', 'description' => 'Sammelkarten lesen, bewerten, sortieren und graden lassen.', 'keywords' => ['Grading', 'Sammeln', 'Seltenheit']],
            ],
        ],
        'technik-wissenschaft' => [
            'name' => 'Technik & Wissenschaft', 'interest' => 3,
            'description' => 'IT, KI, Hardware, Innovationen, Naturwissenschaft, Forschung und Industrie.',
            'keywords' => ['Technik', 'Wissenschaft'],
            'children' => [
                'it-software' => ['name' => 'IT & Software', 'description' => 'Software, Netze, Betrieb und Ausfälle.', 'keywords' => ['Software', 'Netzwerk']],
                'ki-hardware' => ['name' => 'KI & Hardware', 'description' => 'KI-Systeme, Chips, Speicher und Geräte.', 'keywords' => ['KI', 'Hardware', 'RAM']],
                'technik-innovationen' => ['name' => 'Technik & Innovationen', 'description' => 'Wie Technik im Alltag funktioniert und was neu ist.', 'keywords' => ['Innovation', 'Infrastruktur']],
                'naturwissenschaft' => ['name' => 'Naturwissenschaft', 'description' => 'Physik, Chemie, Biologie, Erde und Weltraum.', 'keywords' => ['Physik', 'Chemie', 'Biologie']],
                'forschung-entwicklung' => ['name' => 'Forschung & Entwicklung', 'description' => 'Studien, Laborergebnisse und neue Verfahren.', 'keywords' => ['Studie', 'Forschung']],
                'industrie-produktion' => ['name' => 'Industrie & Produktion', 'description' => 'Wie Produkte hergestellt werden; Automobil- und Werksthemen.', 'keywords' => ['Produktion', 'Volkswagen', 'Porsche', 'Mercedes-Benz', 'Bosch', 'B. Braun']],
            ],
        ],
        'geschichte' => [
            'name' => 'Geschichte', 'interest' => 4,
            'description' => 'Reiche, Kriege, Entdeckungen, Persönlichkeiten, Technikgeschichte sowie Alltag und Kulturgeschichte.',
            'keywords' => ['Geschichte'],
            'children' => [
                'reiche-zivilisationen' => ['name' => 'Reiche & Zivilisationen', 'description' => 'Geschichte von Reichen und Hochkulturen.', 'keywords' => []],
                'kriege-wendepunkte' => ['name' => 'Kriege & Wendepunkte', 'description' => 'Kriege und historische Wendepunkte mit ihren Ursachen und Folgen.', 'keywords' => []],
                'entdeckungen-expeditionen' => ['name' => 'Entdeckungen & Expeditionen', 'description' => 'Entdeckungsreisen und Expeditionen in der Geschichte.', 'keywords' => []],
                'menschen-ideen' => ['name' => 'Menschen & große Ideen', 'description' => 'Persönlichkeiten und Ideen, die Geschichte geprägt haben.', 'keywords' => []],
                'technikgeschichte' => ['name' => 'Technikgeschichte', 'description' => 'Erfindungen, Automobil- und Industriegeschichte.', 'keywords' => ['Erfindung', 'Automobilgeschichte']],
                'alltag-kulturgeschichte' => ['name' => 'Alltag & Kulturgeschichte', 'description' => 'Alltagsgeschichte, Landschaften, Bräuche und kuriose Begebenheiten.', 'keywords' => ['Alltagsgeschichte']],
            ],
        ],
        'buehne-musik' => [
            'name' => 'Bühne & Musik', 'interest' => 2,
            'description' => 'Schauspieler, Musical, Theater, Bands, Songs, Alben und Tourneen.',
            'keywords' => ['Musical', 'Sabrina Weckerlin', 'Kim Fölmli', 'Versengold'],
            'children' => [
                'schauspieler-persoenlichkeiten' => ['name' => 'Schauspieler & Persönlichkeiten', 'description' => 'Porträts von Schauspielern und Künstlern.', 'keywords' => ['Deutsche Schauspieler']],
                'musical-theater' => ['name' => 'Musical & Theater', 'description' => 'Musicals, Inszenierungen und Musicaldarsteller.', 'keywords' => ['Musical']],
                'musik-bands' => ['name' => 'Musik & Bands', 'description' => 'Versengold und andere Bands: Bandgeschichte, Mitglieder, Songs, Alben und Tourneen.', 'keywords' => ['Versengold', 'Folk-Rock']],
                'songs-alben-tourneen' => ['name' => 'Songs, Alben & Tourneen', 'description' => 'Einzelne Songs, Alben und Tourneen.', 'keywords' => ['Album', 'Tournee']],
            ],
        ],
        'wirtschaft-gesellschaft-alltag' => [
            'name' => 'Wirtschaft, Gesellschaft & Alltag', 'interest' => 3,
            'description' => 'Unternehmen, Recht und Verbraucherthemen, Politik, Gesellschaft, Alltag und Kurioses.',
            'keywords' => ['Wirtschaft', 'Gesellschaft'],
            'children' => [
                'wirtschaft-unternehmen' => ['name' => 'Wirtschaft & Unternehmen', 'description' => 'Marken, Geschäftsmodelle und Konzerne.', 'keywords' => ['Marketing', 'Unternehmen']],
                'recht-verbraucher' => ['name' => 'Recht & Verbraucher', 'description' => 'Verbraucherrecht, Dark Patterns, Gesetze im Klartext.', 'keywords' => ['Verbraucherschutz', 'DSGVO']],
                'politik-staat' => ['name' => 'Politik & Staat', 'description' => 'Haushalt, Verwaltung und staatliche Entscheidungen.', 'keywords' => ['Bundeshaushalt', 'Politik']],
                'gesellschaft-bildung' => ['name' => 'Gesellschaft & Bildung', 'description' => 'Schule, Lernen, Familie und gesellschaftliche Entwicklungen.', 'keywords' => ['Schule', 'Bildung']],
                'alltag-konsum' => ['name' => 'Alltag & Konsum', 'description' => 'Produkte, Verfügbarkeit, Preise und Kaufverhalten.', 'keywords' => ['Konsum', 'Verknappung']],
                'kurioses-satire' => ['name' => 'Kurioses & Satire', 'description' => 'Satirische und kuriose Texte über Sprache, Büro und Bürokratie.', 'keywords' => ['Satire']],
            ],
        ],
    ],

    // Alte Kategorie (Slug) → Slug der neuen Kategorie, auf die ihre alte Adresse weiterleitet. Die alte Zeile wird abgeschaltet.
    'retire' => [
        'film-serienwelten' => 'film-serien',
        'animation-gaming' => 'gaming-sammelkarten',
        'schauspiel-musical' => 'buehne-musik',
        'technik-erfindungen' => 'technik-wissenschaft',
        'alltag-kuriositaeten' => 'alltag-kulturgeschichte',
    ],

    // Artikel-ID → Slug der primären Unterkategorie.
    'posts' => [
        25 => 'it-software', 24 => 'recht-verbraucher', 20 => 'pokemon', 19 => 'wirtschaft-unternehmen',
        18 => 'alltag-kulturgeschichte', 15 => 'gaming-branche-geschaeftsmodelle', 16 => 'gaming-branche-geschaeftsmodelle',
        17 => 'videospiele-konsolen', 14 => 'gaming-branche-geschaeftsmodelle', 11 => 'gaming-branche-geschaeftsmodelle',
        10 => 'videospiele-konsolen', 12 => 'wirtschaft-unternehmen', 9 => 'pokemon', 8 => 'videospiele-konsolen',
        5 => 'ki-hardware', 6 => 'fantasy-science-fiction', 7 => 'industrie-produktion', 4 => 'videospiele-konsolen',
        3 => 'disney-parks-resorts', 2 => 'world-of-warcraft',
        41 => 'pokemon', 40 => 'pokemon', 39 => 'pokemon', 37 => 'pokemon', 36 => 'disney-lorcana', 35 => 'disney-lorcana',
        34 => 'disney-lorcana', 33 => 'disney-lorcana', 32 => 'gesellschaft-bildung', 31 => 'pokemon',
        26 => 'politik-staat', 23 => 'gaming-branche-geschaeftsmodelle', 38 => 'pokemon',
    ],

    // Queue-ID → Slug der Unterkategorie.
    'queue' => [
        45 => 'alltag-kulturgeschichte', 50 => 'menschen-ideen', 51 => 'kriege-wendepunkte', 33 => 'technik-innovationen',
        4 => 'disney-parks-resorts', 52 => 'schauspieler-besetzung', 13 => 'schauspieler-persoenlichkeiten', 12 => 'marvel-dc',
        43 => 'kurioses-satire', 44 => 'kurioses-satire', 11 => 'ki-hardware', 9 => 'wirtschaft-unternehmen',
        14 => 'alltag-konsum', 37 => 'alltag-konsum', 8 => 'marvel-dc', 6 => 'disney-lorcana', 55 => 'disney-lorcana',
        56 => 'disney-lorcana', 15 => 'pokemon', 48 => 'sammeln-karten-grading', 49 => 'sammeln-karten-grading',
        17 => 'videospiele-konsolen',
    ],
];
