<?php

namespace Lomkit\Access\Search\Guard;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\AmbiguousSearchIndexException;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\RefusedSearchFilterException;
use Lomkit\Access\Exceptions\RefusedSearchParameterException;
use Lomkit\Access\Exceptions\UnguardableSearchEngineException;
use Lomkit\Access\Exceptions\UnknownSearchIndexException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

/** @internal */
final readonly class SearchGuards
{
    /** @var array<string, SearchGuard> */
    private array $guards;

    public function __construct(private ControlRegistry $registry)
    {
        $guards = [];

        foreach ([new TypesenseSearchGuard, new MeilisearchSearchGuard, new AlgoliaSearchGuard] as $guard) {
            $guards[$guard->searchesField()] = $guard;
        }

        $this->guards = $guards;
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     *
     * @throws InvalidSearchBodyException When the body is not a multi-search body that the guard knows.
     * @throws UnknownSearchIndexException When a search names an index that no known model is searched in.
     * @throws RefusedSearchParameterException When a search sends a parameter that a guarded search refuses.
     * @throws RefusedSearchFilterException When the filter of a search cannot be ANDed with the access filter.
     * @throws AmbiguousSearchIndexException When two models are searched in the same index.
     * @throws UnguardableSearchEngineException When the model of an index searches with an engine that has no guard.
     * @throws MissingControlException When no control is registered for the model of an index.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    public function guard(array $body, ?Model $reader): array
    {
        $guard = $this->select($body);
        $field = $guard->searchesField();
        $searches = $body[$field];

        if (! is_array($searches) || ! array_is_list($searches)) {
            throw InvalidSearchBodyException::notAList($guard->engine(), $field);
        }

        $envelope = $guard->envelope(array_diff_key($body, [$field => true]));
        $indexes = SearchIndexes::discovered($this->registry);
        $filters = [];
        $guarded = [];

        foreach ($searches as $position => $search) {
            if (! is_array($search)) {
                throw InvalidSearchBodyException::notAnObject($guard->engine(), $field, $position);
            }

            $index = $guard->index($search, $position);
            $model = $indexes->model($index);
            $filters[$model::class] ??= $this->filterFor($guard, $model, $index, $reader);
            $guarded[] = $guard->guard($search, $index, $filters[$model::class]);
        }

        return [...$envelope, $field => $guarded];
    }

    /**
     * @param  array<array-key, mixed>  $body
     *
     * @throws InvalidSearchBodyException When the body names no list of searches, or several.
     */
    private function select(array $body): SearchGuard
    {
        $found = array_values(array_intersect_key($this->guards, $body));

        if (count($found) !== 1) {
            throw InvalidSearchBodyException::shape(array_keys($this->guards));
        }

        return $found[0];
    }

    /**
     * @throws UnguardableSearchEngineException When the model searches with an engine that has no guard.
     * @throws InvalidSearchBodyException When the model searches with another engine than the shape of the body.
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    private function filterFor(SearchGuard $guard, Model $model, string $index, ?Model $reader): SearchFilter
    {
        $engine = method_exists($model, 'searchableUsing') ? $model->searchableUsing() : null;
        $kind = SearchTokenEngine::of($engine) ?? throw UnguardableSearchEngineException::for($model, $engine);

        if ($kind !== $guard->engine()) {
            throw InvalidSearchBodyException::mismatchedEngine($guard->engine(), $index, $kind);
        }

        $control = Access::controlFor($model);

        if (! $control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($control);
        }

        return $control->searchFilter($reader);
    }
}
