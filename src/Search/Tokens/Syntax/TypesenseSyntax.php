<?php

namespace Lomkit\Access\Search\Tokens\Syntax;

use Lomkit\Access\Exceptions\UnquotableSearchKeyException;

final class TypesenseSyntax extends FilterSyntax
{
    private const string QUOTE = '`';

    /** @param  list<string>  $quoted */
    #[\Override]
    protected function clause(string $field, array $quoted): string
    {
        return sprintf('%s:=[%s]', $field, implode(', ', $quoted));
    }

    /** @throws UnquotableSearchKeyException When the value holds a backtick, which Typesense cannot escape. */
    #[\Override]
    protected function quote(string $value): string
    {
        if (str_contains($value, self::QUOTE)) {
            throw UnquotableSearchKeyException::typesense($value);
        }

        return self::QUOTE.$value.self::QUOTE;
    }

    #[\Override]
    protected function conjunction(): string
    {
        return ' && ';
    }
}
