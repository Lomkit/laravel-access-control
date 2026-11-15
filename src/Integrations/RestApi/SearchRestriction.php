<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidRestReaderException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedRestSearchException;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Search\UsesControlledSearchable;
use Lomkit\Rest\Extensions\Contexts\EngineRestrictionContext;
use Lomkit\Rest\Extensions\Extension;

/** @internal */
final readonly class SearchRestriction
{
    /** @param  class-string<Extension>  $extension */
    public function __construct(private string $extension) {}

    /**
     * @throws InvalidRestReaderException When the user is not an Eloquent model.
     * @throws UnsupportedRestSearchException When the model holds no access keys, or the engine cannot match them.
     * @throws MissingControlException When no control is registered for the model of the resource.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     */
    public function apply(EngineRestrictionContext $context): void
    {
        if ($context->rowsRestrictedInSql) {
            return;
        }

        $reader = RestReader::of($context->user, $this->extension);
        $model = $context->resource::newModel();
        $engine = $this->engineOf($context, $model);

        if ($engine instanceof NullEngine) {
            $context->query->matchNothing();

            return;
        }

        $control = Access::controlFor($model);

        if (! $control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($control);
        }

        $filter = $control->searchFilter($reader);

        if ($filter->isClosed()) {
            $context->query->matchNothing();

            return;
        }

        foreach ($filter->whereIns() as $field => $keys) {
            $context->query->whereIn($field, $keys);
        }
    }

    /** @throws UnsupportedRestSearchException When the model holds no access keys, or the engine cannot match them. */
    private function engineOf(EngineRestrictionContext $context, Model $model): object
    {
        $resource = $context->resource::class;

        if (! UsesControlledSearchable::check($model)) {
            throw UnsupportedRestSearchException::notControlledSearchable($resource, $model::class, $this->extension);
        }

        $engine = $context->engine;

        if ($engine instanceof NullEngine) {
            return $engine;
        }

        if (! Container::getInstance()->make(TrustedSearchEngines::class)->trusts($engine)) {
            throw UnsupportedRestSearchException::untrustedEngine($resource, $engine, $this->extension);
        }

        if (! $context->matchesStringLists) {
            throw UnsupportedRestSearchException::withoutStringLists($resource, $engine, $this->extension);
        }

        return $engine;
    }
}
