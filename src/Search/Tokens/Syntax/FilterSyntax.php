<?php

namespace Lomkit\Access\Search\Tokens\Syntax;

use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\SearchFilter;

abstract class FilterSyntax
{
    private const string UNMATCHABLE_KEY = '#closed';

    final public function render(SearchFilter $filter): ?string
    {
        $whereIns = $filter->isClosed() ? [SearchField::Access->value => [self::UNMATCHABLE_KEY]] : $filter->whereIns();

        if ($whereIns === []) {
            return null;
        }

        $clauses = [];

        foreach ($whereIns as $field => $values) {
            $clauses[] = $this->clause($field, array_map($this->quote(...), $values));
        }

        return implode($this->conjunction(), $clauses);
    }

    /** @param  list<string>  $quoted */
    abstract protected function clause(string $field, array $quoted): string;

    abstract protected function quote(string $value): string;

    abstract protected function conjunction(): string;
}
