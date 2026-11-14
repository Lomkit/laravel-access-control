<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\Builder;
use Lomkit\Access\Exceptions\ConflictingSearchScopeException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\UndeclaredSearchScopeException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchEngineException;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
final class ControlledSearchBuilder extends Builder
{
    private ?SearchScope $scope = null;

    /**
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsupportedSearchEngineException When the engine of the model cannot filter by the access keys.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    public function controlled(): static
    {
        $reader = Auth::user();

        return $this->declare(ControlledScope::for($this->model, $reader instanceof Model ? $reader : null));
    }

    /**
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsupportedSearchEngineException When the engine of the model cannot filter by the access keys.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    public function controlledFor(?Model $reader): static
    {
        return $this->declare(ControlledScope::for($this->model, $reader));
    }

    public function uncontrolled(): static
    {
        return $this->declare(new UncontrolledScope);
    }

    /** @throws UndeclaredSearchScopeException When the search declares neither controlled() nor uncontrolled(). */
    #[\Override]
    protected function engine(): mixed
    {
        return ($this->scope ?? throw UndeclaredSearchScopeException::for($this->model))->prepare($this, parent::engine());
    }

    /** @throws ConflictingSearchScopeException When the search already declared the other scope. */
    private function declare(SearchScope $scope): static
    {
        if ($this->scope !== null && $scope::class !== $this->scope::class) {
            throw ConflictingSearchScopeException::for($this->model);
        }

        $this->scope = $scope;
        $scope->prepare($this, parent::engine());

        return $this;
    }
}
