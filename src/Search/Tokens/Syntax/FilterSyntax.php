<?php

namespace Lomkit\Access\Search\Tokens\Syntax;

use Lomkit\Access\Exceptions\UndigestedSearchValueException;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\SearchKeyDigest;

abstract class FilterSyntax
{
    private const string UNMATCHABLE_KEY = '#closed';

    /** @throws UndigestedSearchValueException When a value of the filter is not the digest of a search key. */
    final public function render(SearchFilter $filter): ?string
    {
        $whereIns = $filter->isClosed() ? [SearchField::Access->value => [self::UNMATCHABLE_KEY]] : $filter->whereIns();

        if ($whereIns === []) {
            return null;
        }

        $clauses = [];

        foreach ($whereIns as $field => $values) {
            $clauses[] = $this->clause($field, array_map(fn (string $value): string => $this->quote(self::checked($field, $value)), $values));
        }

        return implode($this->conjunction(), $clauses);
    }

    /** @throws UndigestedSearchValueException When the value is neither a digest nor the key that no document carries. */
    private static function checked(string $field, string $value): string
    {
        if ($value !== self::UNMATCHABLE_KEY && ! SearchKeyDigest::isDigest($value)) {
            throw UndigestedSearchValueException::for($field, $value);
        }

        return $value;
    }

    /** @param  list<string>  $quoted */
    abstract protected function clause(string $field, array $quoted): string;

    abstract protected function quote(string $value): string;

    abstract protected function conjunction(): string;
}
