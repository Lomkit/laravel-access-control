<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Exceptions\UncombinableSearchFilterException;

/** @internal */
final readonly class TypesenseAppFilter
{
    private const string OPTION = 'filter_by';

    private function __construct(
        private Model $model,
        private string $source,
        private string $expression,
    ) {}

    /** @throws UncombinableSearchFilterException When the filter is not a string, or does not end on its own. */
    public static function fromOptions(Model $model, mixed $filter): self
    {
        return self::checked($model, 'options()', $filter);
    }

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the filter is not a string, or does not end on its own.
     */
    public static function fromModel(Builder $search): ?self
    {
        $parameters = method_exists($search->model, 'typesenseSearchParameters') ? $search->model->typesenseSearchParameters() : [];

        if (! is_array($parameters) || ! array_key_exists(self::OPTION, $parameters)) {
            return null;
        }

        return self::checked($search->model, 'typesenseSearchParameters()', $parameters[self::OPTION]);
    }

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UncombinableSearchFilterException When the clauses render to a filter that does not end on its own.
     */
    public static function fromClauses(Builder $search, TypesenseEngine $engine): self
    {
        $clauses = new Builder($search->model, $search->query);
        $clauses->wheres = $search->wheres;
        $clauses->whereIns = array_diff_key($search->whereIns, array_flip(SearchField::names()));
        $clauses->whereNotIns = $search->whereNotIns;

        return self::checked($search->model, 'the where() clauses', $engine->buildSearchParameters($clauses, 1, 1)[self::OPTION] ?? '');
    }

    /** @throws UncombinableSearchFilterException When the combined filter would not end with the access filter. */
    public function and(string $access): string
    {
        if (trim($this->expression) === '') {
            return $access;
        }

        $combined = "({$this->expression}) && ({$access})";

        if (! TypesenseFilterBalance::endsWithGroup($combined, $access)) {
            throw UncombinableSearchFilterException::notSelfContained($this->model, $this->source, $this->expression);
        }

        return $combined;
    }

    /** @throws UncombinableSearchFilterException When the filter is not a string, or does not end on its own. */
    private static function checked(Model $model, string $source, mixed $filter): self
    {
        if (! is_string($filter)) {
            throw UncombinableSearchFilterException::notAString($model, $source, $filter);
        }

        if (! TypesenseFilterBalance::isSelfContained($filter)) {
            throw UncombinableSearchFilterException::notSelfContained($model, $source, $filter);
        }

        return new self($model, $source, $filter);
    }
}
