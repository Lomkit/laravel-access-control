<?php

namespace Lomkit\Access\Search;

/** @internal */
final readonly class TypesenseFilterBalance
{
    public static function isSelfContained(string $filter): bool
    {
        $tokens = TypesenseFilterTokenizer::tokenize("({$filter})");

        return $tokens !== null && end($tokens) === ')';
    }

    public static function endsWithGroup(string $filter, string $group): bool
    {
        $tokens = TypesenseFilterTokenizer::tokenize($filter);
        $groupTokens = TypesenseFilterTokenizer::tokenize($group);

        if ($tokens === null || $groupTokens === null) {
            return false;
        }

        $tail = ['&&', '(', ...$groupTokens, ')'];

        return array_slice($tokens, -count($tail)) === $tail;
    }
}
