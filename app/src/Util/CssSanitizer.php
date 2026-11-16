<?php

declare(strict_types=1);

namespace Dispatch\Util;

/**
 * CSS-Whitelist für eingereichte Artikel (ADR-034).
 *
 * Es wird nie gefiltert und weitergereicht, sondern gelesen, geprüft und neu geschrieben: in die Ausgabe kommen nur
 * Selektoren und Werte, die diese Klasse selbst aus validierten Bausteinen zusammensetzt. Ein Tokenizer-Fehler
 * kann deshalb höchstens Gestaltung kosten, nie etwas Gefährliches durchlassen.
 *
 * Jede Regel steht in der Ausgabe auf einer Zeile; wer kürzt, kürzt an Zeilengrenzen.
 */
final class CssSanitizer
{
    public const MAX_BYTES = 24576;

    private const MAX_INPUT_BYTES = 98304;
    private const SCOPED_FACTOR = 4;
    private const MAX_SELECTORS_PER_RULE = 20;
    private const MAX_NESTING = 3;
    private const MAX_Z_INDEX = 10;
    private const NAME_DISPLAY_LENGTH = 60;

    private const OWN_CLASS_PATTERN = '/^x-[a-z0-9-]{1,40}$/';
    private const KEYFRAMES_NAME_PATTERN = '/^x-[a-z0-9-]{1,40}$/';
    private const CUSTOM_PROPERTY_PATTERN = '/^--x-[a-z0-9-]{1,40}$/';
    private const NTH_PATTERN = '/^(odd|even|[+-]?[0-9]{1,3}|[+-]?[0-9]{0,3}n([+-][0-9]{1,3})?)$/';
    private const KEYFRAME_SELECTOR_PATTERN = '/^(from|to|[0-9]{1,3}(\.[0-9]+)?%)$/i';
    private const VALUE_CHARACTERS = '/^[A-Za-z0-9\-_.,#%()\/+*\[\]\x01 ]*\z/';
    private const STRING_SPLIT = '/("[^"\x00-\x1F{};<\\\\]*"|\'[^\'\x00-\x1F{};<\\\\]*\')/';

    private const MEDIA_CONDITION = '\((?:(?:min-|max-)?(?:width|height)\s*:\s*-?[0-9]+(?:\.[0-9]+)?(?:px|em|rem|vw|vh)?'
        . '|prefers-color-scheme\s*:\s*(?:dark|light)|prefers-reduced-motion\s*:\s*(?:reduce|no-preference)'
        . '|orientation\s*:\s*(?:portrait|landscape))\)';
    private const MEDIA_QUERY = '(?:(?:print|screen|all)|' . self::MEDIA_CONDITION . ')(?:\s+and\s+' . self::MEDIA_CONDITION . ')*';

    private const ALLOWED_PSEUDO_CLASSES = ['hover', 'focus-visible', 'first-child', 'last-child'];
    private const ALLOWED_PSEUDO_ELEMENTS = ['before', 'after'];

    private const POSITIONS = ['relative', 'absolute', 'static'];
    private const DISPLAYS = ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'flow-root', 'list-item'];
    private const FONT_FAMILIES = ['var(--serif)', 'var(--sans)', 'var(--mono)', 'serif', 'sans-serif', 'monospace'];
    private const STRING_PROPERTIES = ['content', 'quotes', 'grid-template-areas', 'list-style-type', 'list-style'];

    private const ALLOWED_FUNCTIONS = [
        'rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color', 'color-mix',
        'linear-gradient', 'radial-gradient', 'conic-gradient',
        'repeating-linear-gradient', 'repeating-radial-gradient', 'repeating-conic-gradient',
        'calc', 'min', 'max', 'clamp', 'var',
        'translate', 'translatex', 'translatey', 'translatez', 'translate3d',
        'scale', 'scalex', 'scaley', 'scale3d', 'rotate', 'rotatex', 'rotatey', 'rotatez', 'rotate3d',
        'skew', 'skewx', 'skewy', 'matrix', 'matrix3d', 'perspective',
        'blur', 'brightness', 'contrast', 'drop-shadow', 'grayscale', 'hue-rotate', 'invert', 'opacity', 'saturate', 'sepia',
        'cubic-bezier', 'steps', 'minmax', 'repeat', 'fit-content',
        'inset', 'circle', 'ellipse', 'polygon',
    ];

    private const ALLOWED_PROPERTIES = [
        'color', 'background', 'background-color', 'background-image', 'background-size', 'background-position',
        'background-repeat', 'background-clip', 'background-origin',
        'border-radius', 'box-shadow', 'text-shadow',
        'outline', 'outline-color', 'outline-style', 'outline-width', 'outline-offset',
        'gap', 'row-gap', 'column-gap',
        'font-size', 'font-weight', 'font-style', 'font-family', 'font-variant', 'font-variant-numeric',
        'letter-spacing', 'line-height', 'word-spacing',
        'text-align', 'text-align-last', 'text-transform', 'text-decoration', 'text-decoration-line',
        'text-decoration-color', 'text-decoration-style', 'text-decoration-thickness', 'text-underline-offset',
        'text-indent', 'text-wrap', 'text-overflow', 'white-space', 'overflow-wrap', 'word-break', 'hyphens',
        'list-style', 'list-style-type', 'list-style-position', 'quotes',
        'display', 'flex', 'flex-direction', 'flex-wrap', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-basis',
        'align-items', 'align-self', 'align-content', 'justify-content', 'justify-items', 'justify-self',
        'place-items', 'place-content', 'place-self', 'order',
        'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows', 'grid-template-areas', 'grid-area',
        'grid-column', 'grid-row', 'grid-column-start', 'grid-column-end', 'grid-row-start', 'grid-row-end',
        'grid-auto-flow', 'grid-auto-columns', 'grid-auto-rows',
        'columns', 'column-count', 'column-width', 'column-rule', 'column-rule-width', 'column-rule-style',
        'column-rule-color', 'column-span', 'column-fill',
        'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'aspect-ratio', 'box-sizing',
        'opacity', 'filter', 'clip-path', 'transform', 'transform-origin', 'translate', 'scale', 'rotate',
        'transition', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
        'animation', 'animation-name', 'animation-duration', 'animation-timing-function', 'animation-delay',
        'animation-iteration-count', 'animation-direction', 'animation-fill-mode', 'animation-play-state',
        'position', 'top', 'right', 'bottom', 'left', 'inset', 'z-index',
        'content', 'overflow', 'overflow-x', 'overflow-y', 'object-fit', 'object-position', 'vertical-align',
    ];

    private const PROPERTY_FAMILIES = [
        '/^(margin|padding)(-(top|right|bottom|left|block|inline)(-(start|end))?)?\z/',
        '/^border(-(top|right|bottom|left|block|inline)(-(start|end))?)?(-(width|style|color))?\z/',
        '/^border-(top|bottom|start|end)-(left|right|start|end)-radius\z/',
    ];

    /** @var array<string, int> Hinweistext → Anzahl, in Reihenfolge des ersten Auftretens */
    private array $notes = [];

    private string $scope = '';

    private string $failure = '';

    /**
     * Ein Stylesheet (Inhalt eines `<style>`-Blocks).
     *
     * @param string $scope Selektor, der jeder Regel vorangestellt wird; leer = unverändert (gespeicherte Form)
     */
    public function sanitize(string $css, string $scope = ''): CssResult
    {
        $this->notes = [];
        $this->scope = $scope;

        $source = $this->prepare($css);
        if ($source === '') {
            return new CssResult('', $this->collectNotes());
        }

        $limit = self::MAX_BYTES;
        if ($scope !== '') {
            $limit *= self::SCOPED_FACTOR;
        }

        $kept = [];
        $size = 0;
        foreach ($this->parseRules($source, true) as $statement) {
            if ($size + strlen($statement) + 1 > $limit) {
                $this->note('CSS über ' . intdiv(self::MAX_BYTES, 1024) . ' KB: der Rest wurde entfernt');
                break;
            }
            $kept[] = $statement;
            $size += strlen($statement) + 1;
        }

        return new CssResult(implode("\n", $kept), $this->collectNotes());
    }

    /**
     * Der Wert eines `style`-Attributs. Das Ergebnis hat die Form `eigenschaft:wert;eigenschaft:wert` ohne Klammern.
     */
    public function sanitizeDeclarations(string $declarations): CssResult
    {
        $this->notes = [];
        $this->scope = '';

        $source = $this->prepare($declarations);
        $kept = [];
        if ($source !== '') {
            $kept = $this->parseDeclarations($source);
        }

        return new CssResult(implode(';', $kept), $this->collectNotes());
    }

    private function prepare(string $css): string
    {
        if (!mb_check_encoding($css, 'UTF-8')) {
            $this->note('CSS ist kein gültiges UTF-8 und wurde entfernt');

            return '';
        }
        if (strlen($css) > self::MAX_INPUT_BYTES) {
            $this->note('CSS zu lang: alles über ' . intdiv(self::MAX_INPUT_BYTES, 1024) . ' KB wurde ignoriert');
            $css = substr($css, 0, self::MAX_INPUT_BYTES);
        }

        return trim($this->stripComments($css));
    }

    /** Kommentare werden zu einem Leerzeichen, wie im Browser; Strings bleiben unberührt. */
    private function stripComments(string $css): string
    {
        $output = '';
        $length = strlen($css);
        $position = 0;
        while ($position < $length) {
            $char = $css[$position];
            if ($char === '"' || $char === "'") {
                $end = self::skipString($css, $position);
                $output .= substr($css, $position, $end - $position);
                $position = $end;

                continue;
            }
            if ($char === '/' && ($css[$position + 1] ?? '') === '*') {
                $this->note('CSS-Kommentar entfernt');
                $output .= ' ';
                $end = strpos($css, '*/', $position + 2);
                if ($end === false) {
                    break;
                }
                $position = $end + 2;

                continue;
            }
            $output .= $char;
            $position++;
        }

        return $output;
    }

    /** @return int Position hinter dem schließenden Anführungszeichen; ein unbeendeter String endet vor dem Zeilenumbruch */
    private static function skipString(string $source, int $position): int
    {
        $quote = $source[$position];
        $length = strlen($source);
        $position++;
        while ($position < $length) {
            $char = $source[$position];
            if ($char === '\\') {
                $position += 2;

                continue;
            }
            if ($char === $quote) {
                return $position + 1;
            }
            if ($char === "\n") {
                return $position;
            }
            $position++;
        }

        return $length;
    }

    /**
     * @return list<string> fertige Anweisungen, je eine Zeile
     */
    private function parseRules(string $source, bool $isTopLevel): array
    {
        $statements = [];
        $length = strlen($source);
        $position = 0;
        while (true) {
            while ($position < $length && ctype_space($source[$position])) {
                $position++;
            }
            if ($position >= $length) {
                break;
            }

            [$prelude, $body] = $this->readStatement($source, $position);
            if (str_starts_with($prelude, '@')) {
                $statement = $this->buildAtRule($prelude, $body, $isTopLevel);
            } else {
                $statement = $this->buildRule($prelude, $body);
            }
            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * Liest bis zum `;` oder bis zum Ende des folgenden `{}`-Blocks.
     *
     * @return array{0: string, 1: string|null} Vorspann und Blockinhalt (null ohne Block)
     */
    private function readStatement(string $source, int &$position): array
    {
        $length = strlen($source);
        $start = $position;
        $depth = 0;
        while ($position < $length) {
            $char = $source[$position];
            if ($char === '"' || $char === "'") {
                $position = self::skipString($source, $position);

                continue;
            }
            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && $char === ';') {
                $prelude = trim(substr($source, $start, $position - $start));
                $position++;

                return [$prelude, null];
            } elseif ($depth === 0 && $char === '{') {
                $prelude = trim(substr($source, $start, $position - $start));

                return [$prelude, $this->readBlock($source, $position)];
            }
            $position++;
        }

        return [trim(substr($source, $start)), null];
    }

    /** `$position` steht auf `{`; danach hinter dem passenden `}`. */
    private function readBlock(string $source, int &$position): ?string
    {
        $length = strlen($source);
        $start = $position + 1;
        $depth = 1;
        $position++;
        while ($position < $length) {
            $char = $source[$position];
            if ($char === '"' || $char === "'") {
                $position = self::skipString($source, $position);

                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $body = substr($source, $start, $position - $start);
                    $position++;

                    return $body;
                }
            }
            $position++;
        }

        $this->note('Nicht geschlossener CSS-Block entfernt');

        return null;
    }

    private function buildRule(string $prelude, ?string $body): ?string
    {
        if ($body === null) {
            $this->note('Unvollständige CSS-Regel entfernt');

            return null;
        }

        $selectors = $this->parseSelectorList($prelude, true, false, 0, false);
        if (count($selectors ?? []) > self::MAX_SELECTORS_PER_RULE) {
            $this->note('Regel mit mehr als ' . self::MAX_SELECTORS_PER_RULE . ' Selektoren entfernt');

            return null;
        }
        $declarations = $this->parseDeclarations($body);
        if ($selectors === null || $selectors === [] || $declarations === []) {
            return null;
        }

        $scoped = [];
        foreach ($selectors as $selector) {
            $scoped[] = $this->scope === '' ? $selector : $this->scope . ' ' . $selector;
        }

        return implode(',', $scoped) . '{' . implode(';', $declarations) . '}';
    }

    private function buildAtRule(string $prelude, ?string $body, bool $isTopLevel): ?string
    {
        if (preg_match('/^@([A-Za-z-]+)\s*(.*)\z/s', $prelude, $match) !== 1) {
            $this->note('Ungültige At-Regel entfernt');

            return null;
        }
        $name = strtolower($match[1]);
        $parameters = trim($match[2]);

        if ($isTopLevel && $name === 'media' && $body !== null) {
            return $this->buildMedia($parameters, $body);
        }
        if ($isTopLevel && $name === 'keyframes' && $body !== null) {
            return $this->buildKeyframes($parameters, $body);
        }

        $this->note('At-Regel @' . $this->display($name) . ' entfernt');

        return null;
    }

    private function buildMedia(string $parameters, string $body): ?string
    {
        $normalized = strtolower((string) preg_replace('/\s+/', ' ', $parameters));
        $pattern = '/^' . self::MEDIA_QUERY . '(?:\s*,\s*' . self::MEDIA_QUERY . ')*\z/';
        if (preg_match($pattern, $normalized) !== 1) {
            $this->note('@media-Bedingung „' . $this->display($parameters) . '“ entfernt (erlaubt: Breite, Höhe, prefers-color-scheme, print)');

            return null;
        }

        $rules = $this->parseRules($body, false);
        if ($rules === []) {
            return null;
        }

        return '@media ' . $normalized . '{' . implode('', $rules) . '}';
    }

    private function buildKeyframes(string $name, string $body): ?string
    {
        if (preg_match(self::KEYFRAMES_NAME_PATTERN, $name) !== 1) {
            $this->note('@keyframes „' . $this->display($name) . '“ entfernt (der Name muss mit x- beginnen)');

            return null;
        }

        $frames = [];
        $length = strlen($body);
        $position = 0;
        while (true) {
            while ($position < $length && ctype_space($body[$position])) {
                $position++;
            }
            if ($position >= $length) {
                break;
            }

            [$prelude, $block] = $this->readStatement($body, $position);
            $frame = $this->buildFrame($prelude, $block);
            if ($frame !== null) {
                $frames[] = $frame;
            }
        }
        if ($frames === []) {
            return null;
        }

        return '@keyframes ' . $name . '{' . implode('', $frames) . '}';
    }

    private function buildFrame(string $prelude, ?string $block): ?string
    {
        $selectors = [];
        foreach (explode(',', $prelude) as $part) {
            $part = strtolower(trim($part));
            if (preg_match(self::KEYFRAME_SELECTOR_PATTERN, $part) !== 1) {
                $this->note('Keyframe-Stelle „' . $this->display($part) . '“ entfernt');

                return null;
            }
            $selectors[] = $part;
        }
        if ($block === null) {
            return null;
        }
        $declarations = $this->parseDeclarations($block);
        if ($declarations === []) {
            return null;
        }

        return implode(',', $selectors) . '{' . implode(';', $declarations) . '}';
    }

    /**
     * @return list<string> `eigenschaft:wert`
     */
    private function parseDeclarations(string $block): array
    {
        $declarations = [];
        foreach ($this->splitTopLevel($block, ';') as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $declaration = $this->parseDeclaration($chunk);
            if ($declaration !== null) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    private function parseDeclaration(string $chunk): ?string
    {
        if (preg_match('/[{}]/', $chunk) === 1) {
            $this->note('Verschachtelte CSS-Regel entfernt');

            return null;
        }
        $colon = strpos($chunk, ':');
        if ($colon === false) {
            $this->note('CSS-Zeile ohne Doppelpunkt „' . $this->display($chunk) . '“ entfernt');

            return null;
        }

        $property = strtolower(trim(substr($chunk, 0, $colon)));
        $value = trim(substr($chunk, $colon + 1));
        if (preg_match('/^(--[a-z0-9-]+|[a-z]+(-[a-z]+)*)\z/', $property) !== 1 || !$this->isAllowedProperty($property)) {
            $this->note('CSS-Eigenschaft „' . $this->display($property) . '“ nicht erlaubt, entfernt');

            return null;
        }

        $importantPattern = '/\s*!\s*important\s*\z/i';
        if (preg_match($importantPattern, $value) === 1) {
            $this->note('CSS: !important entfernt');
            $value = (string) preg_replace($importantPattern, '', $value);
        }

        $clean = $this->cleanValue($property, $value);
        if ($clean === null) {
            $this->note('CSS-Eigenschaft „' . $property . '“ entfernt (' . $this->failure . ')');

            return null;
        }

        return $property . ':' . $clean;
    }

    private function isAllowedProperty(string $property): bool
    {
        if (preg_match(self::CUSTOM_PROPERTY_PATTERN, $property) === 1) {
            return true;
        }
        if (in_array($property, self::ALLOWED_PROPERTIES, true)) {
            return true;
        }
        foreach (self::PROPERTY_FAMILIES as $pattern) {
            if (preg_match($pattern, $property) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string|null der neu geschriebene Wert, null = verworfen (Grund in `$this->failure`)
     */
    private function cleanValue(string $property, string $value): ?string
    {
        $parts = preg_split(self::STRING_SPLIT, $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->fail('Wert nicht lesbar');
        }

        $skeleton = '';
        $output = '';
        $stringCount = 0;
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $stringCount++;
                $skeleton .= "\x01";
                $output .= $part;

                continue;
            }
            $collapsed = (string) preg_replace('/\s+/', ' ', $part);
            $skeleton .= $collapsed;
            $output .= $collapsed;
        }
        $skeleton = trim($skeleton);
        $output = trim($output);

        if ($skeleton === '') {
            return $this->fail('leerer Wert');
        }
        if (preg_match(self::VALUE_CHARACTERS, $skeleton) !== 1) {
            return $this->fail('unzulässige Zeichen, etwa \\ < { } ; @ : oder ein unbeendeter Text');
        }
        if ($stringCount > 0 && !in_array($property, self::STRING_PROPERTIES, true)) {
            return $this->fail('Text in Anführungszeichen ist hier nicht erlaubt');
        }
        if (!$this->hasOnlyAllowedFunctions($skeleton)) {
            return null;
        }

        if (!$this->matchesPropertyRules($property, $skeleton)) {
            return null;
        }

        return $output;
    }

    private function hasOnlyAllowedFunctions(string $skeleton): bool
    {
        $depth = 0;
        foreach (str_split($skeleton) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            if ($depth < 0) {
                $this->fail('Klammern nicht ausgewogen');

                return false;
            }
        }
        if ($depth !== 0) {
            $this->fail('Klammern nicht ausgewogen');

            return false;
        }

        preg_match_all('/([A-Za-z_-][A-Za-z0-9_-]*)\(/', $skeleton, $matches);
        foreach ($matches[1] as $function) {
            if (!in_array(strtolower($function), self::ALLOWED_FUNCTIONS, true)) {
                $this->fail('Funktion ' . $this->display($function) . '() nicht erlaubt');

                return false;
            }
        }

        return true;
    }

    private function matchesPropertyRules(string $property, string $skeleton): bool
    {
        $lower = strtolower($skeleton);

        switch ($property) {
            case 'font-family':
                foreach ($this->splitTopLevel($lower, ',') as $family) {
                    if (!in_array(str_replace(' ', '', $family), self::FONT_FAMILIES, true)) {
                        $this->fail('Schrift nur var(--serif), var(--sans), var(--mono), serif, sans-serif oder monospace');

                        return false;
                    }
                }

                return true;
            case 'display':
                if ($lower === 'none') {
                    $this->fail('display:none ist nicht erlaubt, Inhalte dürfen nicht versteckt werden');

                    return false;
                }

                return $this->isOneOf($lower, self::DISPLAYS, 'erlaubt: ' . implode(', ', self::DISPLAYS));
            case 'position':
                return $this->isOneOf($lower, self::POSITIONS, 'erlaubt: relative, absolute, static');
            case 'z-index':
                if (preg_match('/^[0-9]{1,2}\z/', $lower) !== 1 || (int) $lower > self::MAX_Z_INDEX) {
                    $this->fail('z-index nur 0 bis ' . self::MAX_Z_INDEX);

                    return false;
                }

                return true;
            case 'content':
                if (preg_match('/^(normal|none|\x01( \x01)*)\z/', $lower) !== 1) {
                    $this->fail('content nur als Text in Anführungszeichen');

                    return false;
                }

                return true;
            default:
                return true;
        }
    }

    /**
     * @param list<string> $allowed
     */
    private function isOneOf(string $value, array $allowed, string $hint): bool
    {
        if (in_array($value, $allowed, true)) {
            return true;
        }
        $this->fail('Wert „' . $this->display($value) . '“ nicht erlaubt, ' . $hint);

        return false;
    }

    /**
     * @return list<string>|null null nur im strengen Modus, wenn ein Selektor ungültig war
     */
    private function parseSelectorList(string $text, bool $isSubject, bool $isRelative, int $depth, bool $isStrict): ?array
    {
        $selectors = [];
        foreach ($this->splitTopLevel($text, ',') as $part) {
            $selector = $this->parseSelector($part, $isSubject, $isRelative, $depth);
            if ($selector === null) {
                $reason = $this->failure;
                if ($isStrict) {
                    return null;
                }
                $this->note('CSS-Selektor „' . $this->display(trim($part)) . '“ entfernt (' . $reason . ')');

                continue;
            }
            $selectors[] = $selector;
        }
        if ($isStrict && $selectors === []) {
            return null;
        }

        return $selectors;
    }

    /**
     * Zerlegt einen Selektor und schreibt ihn aus den geprüften Teilen neu. Nur Leerzeichen und `>` verbinden;
     * `+` und `~` würden von einer x-Klasse aus auf Geschwister der Artikelseite zeigen (Quellenblock, Kasten „Wie der Artikel entstand“).
     */
    private function parseSelector(string $text, bool $isSubject, bool $isRelative, int $depth): ?string
    {
        $text = trim($text);
        $length = strlen($text);
        $position = 0;
        $output = '';
        $pendingCombinator = '';
        $isFirst = true;
        $firstHasClass = false;

        if ($isRelative && str_starts_with($text, '>')) {
            $output = '> ';
            $position = 1;
            $this->skipSpaces($text, $position);
        }

        while ($position < $length) {
            $compound = $this->parseCompound($text, $position, $depth, $isSubject);
            if ($compound === null) {
                return null;
            }
            if ($isFirst) {
                $firstHasClass = $compound['hasClass'];
                $isFirst = false;
            }
            $output .= $pendingCombinator . $compound['text'];
            if ($compound['isPseudoElement'] && $position < $length) {
                return $this->fail('nach ::before/::after darf nichts folgen');
            }

            $sawSpace = $this->skipSpaces($text, $position);
            if ($position >= $length) {
                break;
            }
            $char = $text[$position];
            if ($char === '+' || $char === '~') {
                return $this->fail('der Kombinator ' . $char . ' ist nicht erlaubt (nur Leerzeichen und >)');
            }
            if ($char === '>') {
                $position++;
                $this->skipSpaces($text, $position);
                if ($position >= $length) {
                    return $this->fail('der Selektor endet auf >');
                }
                $pendingCombinator = ' > ';
            } elseif ($sawSpace) {
                $pendingCombinator = ' ';
            } else {
                return $this->fail('unerlaubtes Zeichen „' . $this->display($char) . '“');
            }
        }

        if ($output === '' || $output === '> ') {
            return $this->fail('leer');
        }
        if ($isSubject && !$firstHasClass) {
            return $this->fail('ein Selektor muss mit einer x-Klasse oder einer Baustein-Klasse beginnen');
        }

        return $output;
    }

    /**
     * @return array{text: string, hasClass: bool, isPseudoElement: bool}|null
     */
    private function parseCompound(string $text, int &$position, int $depth, bool $allowPseudoElement): ?array
    {
        $length = strlen($text);
        $output = '';
        $hasClass = false;
        $isPseudoElement = false;

        while ($position < $length) {
            $char = $text[$position];
            if (ctype_space($char) || $char === '>' || $char === '+' || $char === '~') {
                break;
            }
            if ($isPseudoElement) {
                return $this->fail('nach ::before/::after darf nichts folgen');
            }

            if ($char === '*') {
                if ($output !== '') {
                    return $this->fail('„*“ gehört an den Anfang');
                }
                $output .= '*';
                $position++;
            } elseif ($char === '.') {
                $class = $this->readIdentifier($text, $position + 1);
                if ($class === null || !$this->isAllowedClass($class)) {
                    return $this->fail('Klasse „' . $this->display((string) $class) . '“ nicht erlaubt (erlaubt: x-… und die Baustein-Klassen)');
                }
                $output .= '.' . $class;
                $position += 1 + strlen($class);
                $hasClass = true;
            } elseif ($char === '#') {
                return $this->fail('ID-Selektoren sind nicht erlaubt');
            } elseif ($char === '[') {
                $end = strpos($text, ']', $position);
                $inner = $end === false ? '' : strtolower(trim(substr($text, $position + 1, $end - $position - 1)));
                if ($inner !== 'open') {
                    return $this->fail('Attribut-Selektoren sind nicht erlaubt (außer [open])');
                }
                $output .= '[open]';
                $position = (int) $end + 1;
            } elseif ($char === ':') {
                $pseudo = $this->parsePseudo($text, $position, $depth, $allowPseudoElement);
                if ($pseudo === null) {
                    return null;
                }
                $output .= $pseudo['text'];
                $isPseudoElement = $pseudo['isPseudoElement'];
            } else {
                $element = $this->readIdentifier($text, $position);
                if ($element === null || $output !== '') {
                    return $this->fail('unerlaubtes Zeichen „' . $this->display($char) . '“');
                }
                $element = strtolower($element);
                if (!$this->isAllowedElement($element)) {
                    return $this->fail('Element <' . $this->display($element) . '> ist kein Artikel-Element');
                }
                $output .= $element;
                $position += strlen($element);
            }
        }

        if ($output === '') {
            return $this->fail('leerer Selektor');
        }

        return ['text' => $output, 'hasClass' => $hasClass, 'isPseudoElement' => $isPseudoElement];
    }

    /**
     * @return array{text: string, isPseudoElement: bool}|null
     */
    private function parsePseudo(string $text, int &$position, int $depth, bool $allowPseudoElement): ?array
    {
        $isDoubleColon = substr($text, $position, 2) === '::';
        $nameStart = $position + ($isDoubleColon ? 2 : 1);
        $name = $this->readIdentifier($text, $nameStart);
        if ($name === null) {
            return $this->fail('Pseudo-Selektor nicht lesbar');
        }
        $name = strtolower($name);
        $position = $nameStart + strlen($name);

        if (in_array($name, self::ALLOWED_PSEUDO_ELEMENTS, true)) {
            if (!$allowPseudoElement) {
                return $this->fail('::' . $name . ' nur am Ende eines Selektors');
            }

            return ['text' => '::' . $name, 'isPseudoElement' => true];
        }
        if ($isDoubleColon) {
            return $this->fail('Pseudo-Element ::' . $this->display($name) . ' nicht erlaubt');
        }
        if (in_array($name, self::ALLOWED_PSEUDO_CLASSES, true)) {
            return ['text' => ':' . $name, 'isPseudoElement' => false];
        }
        if (!in_array($name, ['nth-child', 'not', 'is', 'has'], true)) {
            return $this->fail('Pseudo-Klasse :' . $this->display($name) . ' nicht erlaubt');
        }

        $argument = $this->readParenthesized($text, $position);
        if ($argument === null) {
            return $this->fail(':' . $name . ' braucht eine Klammer');
        }
        if ($name === 'nth-child') {
            $compact = strtolower((string) preg_replace('/\s+/', '', $argument));
            if (preg_match(self::NTH_PATTERN, $compact) !== 1) {
                return $this->fail(':nth-child(' . $this->display($argument) . ') nicht erlaubt');
            }

            return ['text' => ':nth-child(' . $compact . ')', 'isPseudoElement' => false];
        }
        if ($depth + 1 > self::MAX_NESTING) {
            return $this->fail('zu tief verschachtelt');
        }

        $inner = $this->parseSelectorList($argument, false, $name === 'has', $depth + 1, true);
        if ($inner === null) {
            return $this->fail('ungültiger Selektor in :' . $name . '(…)');
        }

        return ['text' => ':' . $name . '(' . implode(', ', $inner) . ')', 'isPseudoElement' => false];
    }

    /** `$position` steht auf `(`; danach hinter dem passenden `)`. @return string|null Inhalt der Klammer */
    private function readParenthesized(string $text, int &$position): ?string
    {
        if (($text[$position] ?? '') !== '(') {
            return null;
        }
        $length = strlen($text);
        $depth = 0;
        for ($index = $position; $index < $length; $index++) {
            if ($text[$index] === '(') {
                $depth++;
            } elseif ($text[$index] === ')') {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($text, $position + 1, $index - $position - 1);
                    $position = $index + 1;

                    return $inner;
                }
            }
        }

        return null;
    }

    private function readIdentifier(string $text, int $position): ?string
    {
        if (preg_match('/\G-?[A-Za-z_][A-Za-z0-9_-]*/', $text, $match, 0, $position) !== 1) {
            return null;
        }

        return $match[0];
    }

    private function isAllowedClass(string $class): bool
    {
        return preg_match(self::OWN_CLASS_PATTERN, $class) === 1 || in_array($class, Sanitizer::ALLOWED_CLASSES, true);
    }

    private function isAllowedElement(string $element): bool
    {
        return in_array($element, Sanitizer::ALLOWED_ELEMENTS, true);
    }

    /** @return bool ob Leerraum übersprungen wurde */
    private function skipSpaces(string $text, int &$position): bool
    {
        $start = $position;
        $length = strlen($text);
        while ($position < $length && ctype_space($text[$position])) {
            $position++;
        }

        return $position > $start;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $text, string $separator): array
    {
        $parts = [];
        $length = strlen($text);
        $depth = 0;
        $start = 0;
        $position = 0;
        while ($position < $length) {
            $char = $text[$position];
            if ($char === '"' || $char === "'") {
                $position = self::skipString($text, $position);

                continue;
            }
            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = substr($text, $start, $position - $start);
                $start = $position + 1;
            }
            $position++;
        }
        $parts[] = substr($text, $start);

        return $parts;
    }

    private function fail(string $reason): null
    {
        $this->failure = $reason;

        return null;
    }

    private function note(string $message): void
    {
        $this->notes[$message] = ($this->notes[$message] ?? 0) + 1;
    }

    /**
     * @return list<string>
     */
    private function collectNotes(): array
    {
        $result = [];
        foreach ($this->notes as $message => $count) {
            $result[] = $count > 1 ? $count . '× ' . $message : $message;
        }

        return $result;
    }

    /** Namen stammen aus fremdem CSS; für Hinweise kürzen und von Steuerzeichen befreien. */
    private function display(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        return mb_substr($clean, 0, self::NAME_DISPLAY_LENGTH, 'UTF-8');
    }
}
