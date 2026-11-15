<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;

/** @internal */
final readonly class ResourceChecks
{
    public function check(ResourceCheckContext $context): void
    {
        $model = $context->resource::modelClass();

        Access::controlFor($model);

        if (Gate::getPolicyFor($model) === null) {
            $context->problem(sprintf(
                'its [%s] model has a control but no policy, so the Gate refuses every row of the resource. Call Access::registerPolicies() in a service provider, or give the model a policy that extends [%s].',
                $model,
                ControlledPolicy::class,
            ));
        }
    }
}
