<?php

namespace Lomkit\Access\Search;

/** @internal */
final readonly class TypesenseFilterBalance
{
    private const string QUOTE = '`';

    public static function isSelfContained(string $filter): bool
    {
        $depth = 0;
        $quoted = false;

        foreach (str_split($filter) as $character) {
            $quoted = $character === self::QUOTE ? ! $quoted : $quoted;
            $depth += $quoted ? 0 : match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return ! $quoted && $depth === 0;
    }
}
