<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Container\Container;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Search\SearchEngineKind;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Search\UsesControlledSearchable;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Text\ScoutEngine;

/** @internal */
final readonly class SearchChecks
{
    public function check(ResourceCheckContext $context): void
    {
        if (! $context->resource->text() instanceof ScoutEngine) {
            return;
        }

        $model = $context->resource::newModel();

        if (! method_exists($model, 'searchableUsing')) {
            return;
        }

        $engine = $model->searchableUsing();
        $class = get_debug_type($engine);

        if (! SearchEngineKind::storesDocuments($engine) || $engine instanceof NullEngine) {
            return;
        }

        if (! UsesControlledSearchable::check($model)) {
            $context->problem(sprintf('its controlled [%s] model does not use the ControlledSearchable trait, so its text searches on the [%s] engine are refused. Use ControlledSearchable on the model and reindex it.', $model::class, $class));

            return;
        }

        if (! Container::getInstance()->make(TrustedSearchEngines::class)->trusts($engine)) {
            $context->problem(sprintf('the [%1$s] engine is not trusted to match the access keys, so its text searches are refused. If it matches lists of strings, call Access::trustSearchEngine(%1$s::class) in a service provider.', $class));

            return;
        }

        $control = Access::controlFor($model);

        if (! $control->searchRules()->isSearchable()) {
            $context->problem(sprintf('the boundary of the [%s] control has no search form, so its text searches on the [%s] engine are refused. Declare the boundary with Boundary::where(), or return its search keys from searchKeys().', $control::class, $class));
        }
    }
}
