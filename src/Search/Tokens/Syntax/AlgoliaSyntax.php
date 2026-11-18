<?php

namespace Lomkit\Access\Search\Tokens\Syntax;

final class AlgoliaSyntax extends FilterSyntax
{
    /** @param  list<string>  $quoted */
    #[\Override]
    protected function clause(string $field, array $quoted): string
    {
        return '('.implode(' OR ', array_map(fn (string $value): string => "{$field}:{$value}", $quoted)).')';
    }

    #[\Override]
    protected function quote(string $value): string
    {
        return "'{$value}'";
    }

    #[\Override]
    protected function conjunction(): string
    {
        return ' AND ';
    }
}
