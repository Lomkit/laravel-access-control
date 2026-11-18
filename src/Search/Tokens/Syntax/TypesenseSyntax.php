<?php

namespace Lomkit\Access\Search\Tokens\Syntax;

final class TypesenseSyntax extends FilterSyntax
{
    /** @param  list<string>  $quoted */
    #[\Override]
    protected function clause(string $field, array $quoted): string
    {
        return sprintf('%s:=[%s]', $field, implode(', ', $quoted));
    }

    #[\Override]
    protected function quote(string $value): string
    {
        return "`{$value}`";
    }

    #[\Override]
    protected function conjunction(): string
    {
        return ' && ';
    }
}
