<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;

final class ControlledScope implements SearchScope
{
    private ?SearchFilter $filter = null;

    private ?Closure $constraint = null;

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
    public function prepare(Builder $search, mixed $engine): void
    {
        match (SearchEngineKind::of($engine, $search->model)) {
            SearchEngineKind::Database => $this->constrainQuery($search),
            SearchEngineKind::Index => $this->filterIndex($search),
        };
    }

    /**
     * @param  Builder<covariant Model>  $search
     *
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    private function filterIndex(Builder $search): void
    {
        if (! $this->control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($this->control);
        }

        $this->filter ??= $this->control->searchFilter($this->reader);

        if ($this->filter->isClosed()) {
            $search->callback = EmptySearchPage::callback();

            return;
        }

        foreach ($this->filter->whereIns() as $field => $values) {
            $search->whereIn($field, $values);
        }
    }

    /** @param  Builder<covariant Model>  $search */
    private function constrainQuery(Builder $search): void
    {
        if ($this->constraint !== null && $search->queryCallback === $this->constraint) {
            return;
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
    }
}
