<?php

declare(strict_types=1);

namespace Dispatch\Site;

use Dispatch\Util\Sanitizer;
use Dom\Element;

/**
 * Liest die Datentabelle einer `figure.chart` in eine Struktur je Diagramm-Typ.
 *
 * Formfehler landen als Texte in `errors`, nie als Ausnahme; bei Fehlern ist `data` leer. Keine Ausgabe, keine Zeichnung.
 */
final class ChartData
{
    private const CATEGORY_TYPES = ['bar', 'column', 'line', 'area', 'stacked-bar', 'stacked-column', 'radar'];
    private const PART_TYPES = ['pie', 'donut', 'waffle'];
    private const MAX_PARTS = 6;
    private const MAX_SERIES = 8;
    private const MAX_ROWS = 40;
    private const MAX_LINE_ROWS = 120;
    private const RADAR_SERIES = 3;
    private const RADAR_AXES = 12;
    private const MAX_WATERFALL_ROWS = 40;
    private const MAX_MATRIX_SIZE = 24;
    private const MAX_CALENDAR_DAYS = 366;
    private const MAX_LINKS = 40;
    private const MAX_LEAVES = 60;
    private const MAX_POINTS = 60;
    private const MAX_CATEGORIES = 8;
    private const MAX_REGIONS = 250;

    private const NUMBER_PATTERN = '/^[-+]?[0-9]+([.,][0-9]+)?\z/';
    private const DATE_PATTERN = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/';
    private const DE_REGIONS = [
        'DE-BW', 'DE-BY', 'DE-BE', 'DE-BB', 'DE-HB', 'DE-HH', 'DE-HE', 'DE-MV',
        'DE-NI', 'DE-NW', 'DE-RP', 'DE-SL', 'DE-SN', 'DE-ST', 'DE-SH', 'DE-TH',
    ];
    /** ISO-3166-1-Alpha-2-Codes (Länder der Weltkarte) */
    private const WORLD_REGIONS = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ '
        . 'CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR '
        . 'GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP '
        . 'KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ '
        . 'NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW '
        . 'SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ '
        . 'UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW';

    /** @var list<string> */
    private array $errors = [];

    private function __construct()
    {
    }

    /**
     * @return array{type: string, data: array<string, mixed>, errors: list<string>}
     */
    public static function fromFigure(Element $figure): array
    {
        $reader = new self();
        $type = (string) $figure->getAttribute('data-type');
        $data = $reader->read($figure, $type);

        return ['type' => $type, 'data' => $reader->errors === [] ? $data : [], 'errors' => $reader->errors];
    }

    /** @return array<string, mixed> */
    private function read(Element $figure, string $type): array
    {
        if (!in_array($type, Sanitizer::CHART_TYPES, true)) {
            $this->errors[] = 'Der Typ fehlt oder ist unbekannt (erlaubt: ' . implode(', ', Sanitizer::CHART_TYPES) . ').';

            return [];
        }

        $table = $this->readTable($figure);
        if ($table === null) {
            return [];
        }

        return match (true) {
            in_array($type, self::CATEGORY_TYPES, true) => $this->categorySeries($type, $table),
            in_array($type, self::PART_TYPES, true) => $this->parts($table),
            $type === 'waterfall' => $this->waterfall($table),
            $type === 'heatmap' => $this->matrix($table),
            $type === 'calendar' => $this->calendar($table),
            $type === 'sankey' => $this->links($table),
            $type === 'treemap' => $this->hierarchy($table),
            $type === 'bubble' => $this->points($table),
            default => $this->regions($figure, $table),
        };
    }

    /** @return array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>}|null */
    private function readTable(Element $figure): ?array
    {
        $table = $figure->querySelector('table');
        if ($table === null) {
            $this->errors[] = 'Die Tabelle mit den Daten fehlt.';

            return null;
        }

        $header = null;
        $rows = [];
        foreach ($table->querySelectorAll('tr') as $row) {
            $cells = [];
            foreach ($row->querySelectorAll('th, td') as $cell) {
                $cells[] = trim((string) preg_replace('/\s+/u', ' ', $cell->textContent));
            }
            if ($header === null) {
                $header = $cells;
                continue;
            }
            $rows[] = ['cells' => $cells, 'sum' => $row->getAttribute('data-sum') === 'true'];
        }

        if ($header === null || $rows === []) {
            $this->errors[] = 'Die Tabelle braucht eine Kopfzeile und mindestens eine Datenzeile.';

            return null;
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Lesbare Zahl: `7.8` oder `7,8`; Tausendertrenner, Leerzeichen und Einheiten sind keine Zahl.
     */
    private function number(string $cell): ?float
    {
        if (preg_match(self::NUMBER_PATTERN, $cell) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $cell);
    }

    /**
     * Wert der Zelle oder null mit Fehlertext (Einheit gehört in data-unit, nicht in die Zelle).
     */
    private function numberAt(array $cells, int $column, int $rowNumber, bool $nonNegative): ?float
    {
        $cell = $cells[$column] ?? '';
        $value = $this->number($cell);
        if ($value === null) {
            $this->errors[] = sprintf('Datenzeile %d, Spalte %d: „%s“ ist keine Zahl. Schreib 7.8 oder 7,8 ohne Tausendertrenner und ohne Einheit (die gehört in data-unit).', $rowNumber, $column + 1, $cell);

            return null;
        }
        if ($nonNegative && $value < 0) {
            $this->errors[] = sprintf('Datenzeile %d: negative Werte sind bei diesem Typ nicht darstellbar.', $rowNumber);

            return null;
        }

        return $value;
    }

    /** @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table */
    private function requireColumns(array $table, int $columns, string $expected): bool
    {
        $valid = count($table['header']) === $columns;
        foreach ($table['rows'] as $row) {
            if (count($row['cells']) !== $columns) {
                $valid = false;
            }
        }
        if (!$valid) {
            $this->errors[] = sprintf('Die Tabelle passt nicht zum Typ: erwartet werden genau %d Spalten (%s) in Kopf- und Datenzeilen.', $columns, $expected);
        }

        return $valid;
    }

    private function limit(int $count, int $max, string $what): void
    {
        if ($count > $max) {
            $this->errors[] = sprintf('Zu viele %s: %d, erlaubt sind höchstens %d.', $what, $count, $max);
        }
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function categorySeries(string $type, array $table): array
    {
        $seriesCount = count($table['header']) - 1;
        if ($seriesCount < 1 || !$this->hasUniformWidth($table, $seriesCount + 1)) {
            $this->errors[] = 'Die Tabelle passt nicht zum Typ: erwartet werden eine Beschriftungsspalte und mindestens eine Wertspalte, in allen Zeilen gleich breit.';

            return [];
        }

        $isRadar = $type === 'radar';
        $this->limit($seriesCount, $isRadar ? self::RADAR_SERIES : self::MAX_SERIES, 'Reihen');
        $maxRows = match (true) {
            $isRadar => self::RADAR_AXES,
            $type === 'line' => self::MAX_LINE_ROWS,
            default => self::MAX_ROWS,
        };
        $this->limit(count($table['rows']), $maxRows, $isRadar ? 'Achsen' : 'Zeilen');

        $rows = [];
        foreach ($table['rows'] as $index => $row) {
            $values = [];
            for ($column = 1; $column <= $seriesCount; $column++) {
                $values[] = $this->numberAt($row['cells'], $column, $index + 1, false);
            }
            $rows[] = ['label' => $row['cells'][0], 'values' => $values];
        }

        return ['series' => array_slice($table['header'], 1), 'rows' => $rows];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function parts(array $table): array
    {
        if (!$this->requireColumns($table, 2, 'Beschriftung, Wert')) {
            return [];
        }
        if (count($table['rows']) > self::MAX_PARTS) {
            $this->errors[] = sprintf('Mehr als %d Teile (%d) lesen sich schlecht. Fasse den Rest zu „Sonstige“ zusammen.', self::MAX_PARTS, count($table['rows']));
        }

        $parts = [];
        foreach ($table['rows'] as $index => $row) {
            $parts[] = ['label' => $row['cells'][0], 'value' => $this->numberAt($row['cells'], 1, $index + 1, true)];
        }

        return ['parts' => $parts];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function waterfall(array $table): array
    {
        if (!$this->requireColumns($table, 2, 'Beschriftung, Wert')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_WATERFALL_ROWS, 'Zeilen');

        $steps = [];
        foreach ($table['rows'] as $index => $row) {
            $steps[] = [
                'label' => $row['cells'][0],
                'value' => $this->numberAt($row['cells'], 1, $index + 1, false),
                'sum' => $row['sum'],
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function matrix(array $table): array
    {
        $columnCount = count($table['header']) - 1;
        if ($columnCount < 1 || !$this->hasUniformWidth($table, $columnCount + 1)) {
            $this->errors[] = 'Die Tabelle passt nicht zum Typ: erwartet werden eine Namensspalte und mindestens eine Wertspalte, in allen Zeilen gleich breit.';

            return [];
        }
        $this->limit($columnCount, self::MAX_MATRIX_SIZE, 'Spalten');
        $this->limit(count($table['rows']), self::MAX_MATRIX_SIZE, 'Zeilen');

        $rows = [];
        foreach ($table['rows'] as $index => $row) {
            $values = [];
            for ($column = 1; $column <= $columnCount; $column++) {
                $values[] = $this->numberAt($row['cells'], $column, $index + 1, false);
            }
            $rows[] = ['label' => $row['cells'][0], 'values' => $values];
        }

        return ['columns' => array_slice($table['header'], 1), 'rows' => $rows];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function calendar(array $table): array
    {
        if (!$this->requireColumns($table, 2, 'Datum JJJJ-MM-TT, Wert')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_CALENDAR_DAYS, 'Tage');

        $days = [];
        $years = [];
        foreach ($table['rows'] as $index => $row) {
            $date = $row['cells'][0];
            if (preg_match(self::DATE_PATTERN, $date, $match) !== 1 || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
                $this->errors[] = sprintf('Datenzeile %d: „%s“ ist kein Datum der Form JJJJ-MM-TT.', $index + 1, $date);
                continue;
            }
            $years[$match[1]] = true;
            $days[] = ['date' => $date, 'value' => $this->numberAt($row['cells'], 1, $index + 1, false)];
        }
        if (count($years) > 1) {
            $this->errors[] = 'Ein Kalender zeigt genau ein Kalenderjahr, die Tabelle enthält ' . count($years) . '.';
        }

        return ['days' => $days];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function links(array $table): array
    {
        if (!$this->requireColumns($table, 3, 'Von, Nach, Wert')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_LINKS, 'Verbindungen');

        $links = [];
        $graph = [];
        foreach ($table['rows'] as $index => $row) {
            [$from, $to] = $row['cells'];
            $links[] = ['from' => $from, 'to' => $to, 'value' => $this->numberAt($row['cells'], 2, $index + 1, true)];
            $graph[$from][] = $to;
        }
        if ($this->hasCycle($graph)) {
            $this->errors[] = 'Ein Sankey-Diagramm darf keine Kreisläufe haben (Von und Nach führen im Kreis zurück).';
        }

        return ['links' => $links];
    }

    /** @param array<string, list<string>> $graph */
    private function hasCycle(array $graph): bool
    {
        $state = [];
        $visit = function (string $node) use (&$visit, &$state, $graph): bool {
            if (($state[$node] ?? 0) === 1) {
                return true;
            }
            if (($state[$node] ?? 0) === 2) {
                return false;
            }
            $state[$node] = 1;
            foreach ($graph[$node] ?? [] as $next) {
                if ($visit($next)) {
                    return true;
                }
            }
            $state[$node] = 2;

            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($visit((string) $node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function hierarchy(array $table): array
    {
        if (!$this->requireColumns($table, 3, 'Gruppe, Untergruppe, Wert')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_LEAVES, 'Blätter');

        $leaves = [];
        foreach ($table['rows'] as $index => $row) {
            $leaves[] = [
                'group' => $row['cells'][0],
                'label' => $row['cells'][1],
                'value' => $this->numberAt($row['cells'], 2, $index + 1, true),
            ];
        }

        return ['leaves' => $leaves];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function points(array $table): array
    {
        if (!$this->requireColumns($table, 5, 'Name, x, y, Größe, Kategorie')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_POINTS, 'Zeilen');

        $points = [];
        $categories = [];
        foreach ($table['rows'] as $index => $row) {
            $categories[$row['cells'][4]] = true;
            $points[] = [
                'name' => $row['cells'][0],
                'x' => $this->numberAt($row['cells'], 1, $index + 1, false),
                'y' => $this->numberAt($row['cells'], 2, $index + 1, false),
                'size' => $this->numberAt($row['cells'], 3, $index + 1, true),
                'category' => $row['cells'][4],
            ];
        }
        $this->limit(count($categories), self::MAX_CATEGORIES, 'Kategorien');

        return ['points' => $points];
    }

    /**
     * @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table
     * @return array<string, mixed>
     */
    private function regions(Element $figure, array $table): array
    {
        $map = (string) $figure->getAttribute('data-map');
        if (!in_array($map, Sanitizer::CHART_MAPS, true)) {
            $this->errors[] = 'Eine Karte braucht data-map (' . implode(' oder ', Sanitizer::CHART_MAPS) . ').';

            return [];
        }
        if (!$this->requireColumns($table, 3, 'Region, Wert, Name')) {
            return [];
        }
        $this->limit(count($table['rows']), self::MAX_REGIONS, 'Regionen');

        $known = $map === 'de' ? self::DE_REGIONS : explode(' ', self::WORLD_REGIONS);
        $regions = [];
        foreach ($table['rows'] as $index => $row) {
            $code = $row['cells'][0];
            if (!in_array($code, $known, true)) {
                $this->errors[] = sprintf('Datenzeile %d: „%s“ ist kein Regionscode der Karte „%s“.', $index + 1, $code, $map);
            }
            $regions[] = ['code' => $code, 'value' => $this->numberAt($row['cells'], 1, $index + 1, false), 'name' => $row['cells'][2]];
        }

        return ['map' => $map, 'regions' => $regions];
    }

    /** @param array{header: list<string>, rows: list<array{cells: list<string>, sum: bool}>} $table */
    private function hasUniformWidth(array $table, int $width): bool
    {
        foreach ($table['rows'] as $row) {
            if (count($row['cells']) !== $width) {
                return false;
            }
        }

        return true;
    }
}
