<?php

namespace Lomkit\Access\Search;

/** @internal */
final readonly class TypesenseFilterTokenizer
{
    private const array OPERATORS = ['&' => '&&', '|' => '||'];

    /** @return list<string>|null */
    public static function tokenize(string $filter): ?array
    {
        $tokens = [];
        $size = strlen($filter);
        $index = 0;

        while ($index < $size) {
            $character = $filter[$index];

            if ($character === ' ') {
                $index++;

                continue;
            }

            $token = match (true) {
                $character === '(', $character === ')' => self::delimiter($character, $index),
                isset(self::OPERATORS[$character]) => self::operator($filter, $index),
                self::startsReference($filter, $index) => self::reference($filter, $index),
                default => TypesenseFilterValue::read($filter, $index),
            };

            if ($token === null) {
                return null;
            }

            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private static function delimiter(string $character, int &$index): string
    {
        $index++;

        return $character;
    }

    private static function operator(string $filter, int &$index): ?string
    {
        $character = $filter[$index];

        if (($filter[$index + 1] ?? '') !== $character) {
            return null;
        }

        $index += 2;

        return self::OPERATORS[$character];
    }

    private static function startsReference(string $filter, int $index): bool
    {
        return $filter[$index] === '$' || ($filter[$index] === '!' && ($filter[$index + 1] ?? '') === '$');
    }

    private static function reference(string $filter, int &$index): ?string
    {
        $size = strlen($filter);
        $start = $index;
        $parenthesis = strpos($filter, '(', $index + 1);

        if ($index + 2 >= $size || $parenthesis === false) {
            return null;
        }

        $index = $parenthesis;
        $depth = 1;

        while (++$index < $size && $depth > 0) {
            $depth += match ($filter[$index]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
        }

        return $depth === 0 ? substr($filter, $start, $index - $start) : null;
    }
}
