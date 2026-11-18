<?php

namespace Lomkit\Access\Search;

/** @internal */
final readonly class TypesenseFilterValue
{
    private const string QUOTE = '`';

    public static function read(string $filter, int &$index): ?string
    {
        $size = strlen($filter);
        $start = $index;
        $quoted = false;
        $afterColon = false;
        $geo = false;
        $character = $filter[$index];

        do {
            $afterColon = $afterColon || $character === ':';
            $geo = $geo && $character !== ')';

            if (! $quoted && ! $afterColon && $character === '{' && $index > 0 && $filter[$index - 1] === '.') {
                return self::closeObject($filter, $index) ? trim(substr($filter, $start, $index - $start)) : null;
            }

            $character = $filter[++$index] ?? '';
            $quoted = $character === self::QUOTE ? ! $quoted : $quoted;

            if ($afterColon && $character === '(') {
                $geo = true;
                $afterColon = false;
            } elseif ($afterColon && $character === '[' && self::opensGeoList($filter, $index)) {
                return self::closeGeoList($filter, $index) ? trim(substr($filter, $start, $index - $start)) : null;
            } elseif ($afterColon && $character !== ' ') {
                $afterColon = false;
            }
        } while ($index < $size && ($quoted || $geo || ! self::endsValue($filter, $index)));

        return trim(substr($filter, $start, $index - $start));
    }

    private static function endsValue(string $filter, int $index): bool
    {
        $character = $filter[$index];
        $next = $filter[$index + 1] ?? '';

        return $character === '(' || $character === ')' || ($character === '&' && $next === '&') || ($character === '|' && $next === '|');
    }

    private static function closeObject(string $filter, int &$index): bool
    {
        $size = strlen($filter);

        while (++$index < $size) {
            if ($filter[$index] === '{') {
                return false;
            }

            if ($filter[$index] === '}') {
                $index++;

                return true;
            }
        }

        return false;
    }

    private static function opensGeoList(string $filter, int $index): bool
    {
        $size = strlen($filter);

        while (++$index < $size && $filter[$index] === ' ');

        return $index < $size && $filter[$index] === '(';
    }

    private static function closeGeoList(string $filter, int &$index): bool
    {
        $size = strlen($filter);
        $depth = 1;

        while (++$index < $size && $depth > 0) {
            $depth += match ($filter[$index]) {
                '[' => 1,
                ']' => -1,
                default => 0,
            };
        }

        return $depth === 0;
    }
}
