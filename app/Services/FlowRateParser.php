<?php

namespace App\Services;

/**
 * Parses the volume (liters) and time (seconds) typed by the surveyors of the
 * water sources, used to compute the flow rate.
 *
 * The parser is conservative: it recognizes only explicit forms and returns
 * null for anything else, so an ambiguous value produces N/A instead of a
 * wrong number obtained by gluing separate digits together (e.g. "0 45").
 */
class FlowRateParser
{
    private const VOLUME_UNITS = '(?:litri|litro|lt\.?|l)';

    // A single quote and ™ are typos of the seconds sign on phone keyboards (e.g. "11'", "6™")
    private const TIME_UNITS = '(?:secondi|secondo|s|\'\'|\'|"|™)';

    /**
     * Returns the volume in liters, or null if the value is not interpretable.
     */
    public static function parseVolume(mixed $value): ?float
    {
        $value = self::normalize($value);
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^(.*?)\s*ml$/', $value, $matches)) {
            $milliliters = self::parseDecimal($matches[1]);

            return $milliliters === null ? null : $milliliters / 1000;
        }

        $value = preg_replace('/\s*'.self::VOLUME_UNITS.'$/', '', $value);

        return self::parseDecimal($value);
    }

    /**
     * Returns the time in seconds, or null if the value is not interpretable.
     */
    public static function parseTime(mixed $value): ?float
    {
        $value = self::normalize($value);
        if (! is_string($value)) {
            return $value;
        }

        // mm:ss or mm:ss.cc
        if (preg_match('/^(\d{1,2}):(\d{1,2}(?:[.,]\d+)?)$/', $value, $matches)) {
            return (int) $matches[1] * 60 + (float) str_replace(',', '.', $matches[2]);
        }

        // mm ss cc, as typed from a stopwatch
        if (preg_match('/^(\d{2}) (\d{2}) (\d{2})$/', $value, $matches)) {
            return (int) $matches[1] * 60 + (int) $matches[2] + (int) $matches[3] / 100;
        }

        $value = preg_replace('/^'.self::TIME_UNITS.'\s*|\s*'.self::TIME_UNITS.'$/', '', $value);

        return self::parseDecimal($value);
    }

    /**
     * Returns a float for numeric input, null for empty or non scalar input,
     * otherwise the trimmed lowercase string to be parsed.
     */
    private static function normalize(mixed $value): float|string|null
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    /**
     * Accepts an integer or a decimal number with "," or "." as separator,
     * optionally followed by a space ("1, 46") or without the integer part (".6").
     * A leading "o" before the separator is read as zero ("o.5").
     */
    private static function parseDecimal(string $value): ?float
    {
        $value = preg_replace('/^o(?=\s*[.,])/', '0', trim($value));

        if (preg_match('/^\d+$/', $value)) {
            return (float) $value;
        }

        if (preg_match('/^(\d*)\s*[.,]\s*(\d+)$/', $value, $matches)) {
            return (float) (($matches[1] === '' ? '0' : $matches[1]).'.'.$matches[2]);
        }

        return null;
    }
}
