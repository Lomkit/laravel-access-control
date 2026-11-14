<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;

final class ControlledScope implements SearchScope
{
    private ?SearchFilter $filter = null;

    private ?Closure $constraint = null;

    private ?ContainsAnyFilter $containsAny = null;

    private function __construct(
        private readonly Control $control,
        private readonly ?Model $reader,
    ) {}

    public static function for(Model $model, ?Model $reader): self
    {
        return new self(Access::controlFor($model), $reader);
    }

    /** @param  Builder<covariant Model>  $search */
    #[\Override]
    public function prepare(Builder $search, mixed $engine): mixed
    {
        return match (SearchEngineKind::of($engine, $search->model)) {
            SearchEngineKind::Database => $this->constrainQuery($search, $engine),
            SearchEngineKind::Index => $this->filterIndex($search, $engine),
            SearchEngineKind::NativeFilters => $this->filterNatively($search, $engine),
        };
    }

    /** @throws UnsearchableBoundaryException When the boundary of the control has no search form. */
    private function filter(): SearchFilter
    {
        if (! $this->control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($this->control);
        }

        return $this->filter ??= $this->control->searchFilter($this->reader);
    }

    /** @param  Builder<covariant Model>  $search */
    private function filterIndex(Builder $search, mixed $engine): mixed
    {
        $filter = $this->filter();

        if ($filter->isClosed()) {
            $search->callback = EmptySearchPage::callback();

            return $engine;
        }

        foreach ($filter->whereIns() as $field => $values) {
            $search->whereIn($field, $values);
        }

        return $engine;
    }

    /** @param  Builder<covariant Model>  $search */
    private function filterNatively(Builder $search, mixed $engine): mixed
    {
        $filter = $this->filter();

        if ($filter->isClosed()) {
            return new NullEngine;
        }

        ($this->containsAny ??= new ContainsAnyFilter)->apply($search, $filter);

        return $engine;
    }

    /** @param  Builder<covariant Model>  $search */
    private function constrainQuery(Builder $search, mixed $engine): mixed
    {
        if ($this->constraint !== null && $search->queryCallback === $this->constraint) {
            return $engine;
        }

        $callback = $search->queryCallback;

        $this->constraint = function (EloquentBuilder $query) use ($callback): void {
            if (is_callable($callback)) {
                $callback($query);
            }

            ConstraintGroup::wrap($query);

            $this->control->restrict($query, $this->reader);
        };

        $search->queryCallback = $this->constraint;

        return $engine;
    }
}
