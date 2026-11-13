<?php

declare(strict_types=1);

namespace Dispatch\Site;

/**
 * Zahlen, Achsenschritte und Texte für die Diagramm-Zeichnung. Reine Rechenfunktionen ohne DOM.
 */
final class ChartFormat
{
    /** Schmales geschütztes Leerzeichen zwischen Zahl und Einheit sowie in Tausendergruppen. */
    public const THIN_SPACE = "\u{202F}";
    public const MINUS = "\u{2212}";
    private const MAX_DECIMALS = 2;
    private const MAX_INTERVALS = 6;
    private const STEP_FACTORS = [1, 2, 5];
    private const CHARACTER_WIDTH = 0.56;

    /** Zahl mit Komma; Tausendergruppen erst ab fünf Stellen („9000“, aber „12 000“). */
    public static function number(float $value, int $decimals = 0): string
    {
        $digits = number_format(abs($value), $decimals, '.', '');
        [$integer, $fraction] = array_pad(explode('.', $digits), 2, '');
        if (strlen($integer) > 4) {
            $integer = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', self::THIN_SPACE, $integer);
        }
        $isNegative = $value < 0 && (float) $digits !== 0.0;

        return ($isNegative ? self::MINUS : '') . $integer . ($fraction === '' ? '' : ',' . $fraction);
    }

    /** Zahl mit angehängter Einheit; die Einheit steht nie in der Zelle, nur hier. */
    public static function withUnit(float $value, int $decimals, string $unit): string
    {
        return self::number($value, $decimals) . ($unit === '' ? '' : self::THIN_SPACE . $unit);
    }

    /** Kleinste Nachkommastellenzahl (höchstens zwei), die alle Werte genau genug zeigt. */
    public static function decimalsOf(array $values): int
    {
        $decimals = 0;
        foreach ($values as $value) {
            $places = 0;
            while ($places < self::MAX_DECIMALS && abs(round((float) $value, $places) - (float) $value) > 1e-9) {
                $places++;
            }
            $decimals = max($decimals, $places);
        }

        return $decimals;
    }

    /** Koordinate mit höchstens einer Nachkommastelle, ohne überflüssige Nullen. */
    public static function coordinate(float $value): string
    {
        $text = number_format(round($value, 1), 1, '.', '');
        $text = rtrim(rtrim($text, '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    /**
     * Schöne Achsenschritte (1, 2 oder 5 mal Zehnerpotenz), höchstens sechs Intervalle; die Achse schließt die Null ein.
     *
     * @return array{min: float, max: float, step: float, decimals: int}
     */
    public static function niceScale(float $lowest, float $highest): array
    {
        $low = min(0.0, $lowest);
        $high = max(0.0, $highest);
        if ($high - $low <= 0.0) {
            $high = 1.0;
        }
        $range = $high - $low;

        for ($power = 10 ** floor(log10($range / self::MAX_INTERVALS)); ; $power *= 10) {
            foreach (self::STEP_FACTORS as $factor) {
                $step = $factor * $power;
                if ($range / $step <= self::MAX_INTERVALS + 1e-9) {
                    return [
                        'min' => floor($low / $step + 1e-9) * $step,
                        'max' => ceil($high / $step - 1e-9) * $step,
                        'step' => $step,
                        'decimals' => $step >= 1 ? 0 : (int) -floor(log10($step)),
                    ];
                }
            }
        }
    }

    /**
     * Teilt $total so auf die Werte auf, dass die ganzen Anteile genau $total ergeben (Größte-Rest-Verfahren).
     *
     * @param list<float> $values
     * @return list<int>
     */
    public static function largestRemainder(array $values, int $total = 100): array
    {
        $sum = array_sum($values);
        if ($sum <= 0) {
            return array_fill(0, count($values), 0);
        }

        $shares = [];
        $remainders = [];
        foreach ($values as $index => $value) {
            $raw = $value / $sum * $total;
            $shares[$index] = (int) floor($raw);
            $remainders[$index] = $raw - $shares[$index];
        }
        $missing = $total - array_sum($shares);
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $missing) as $index) {
            $shares[$index]++;
        }
        ksort($shares);

        return array_values($shares);
    }

    /** @return list<string> */
    public static function wrap(string $text, int $width): array
    {
        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = trim($current . ' ' . $word);
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
                continue;
            }
            $current = $candidate;
        }
        $lines[] = $current;

        return $lines;
    }

    public static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, max(1, $length - 1))) . '…';
    }

    /** Grobe Textbreite in Zeichnungseinheiten, um zu entscheiden, ob eine Beschriftung passt. */
    public static function textWidth(string $text, float $fontSize = 12.5): float
    {
        return mb_strlen($text) * $fontSize * self::CHARACTER_WIDTH;
    }
}
