<?php

namespace Lomkit\Access\Integrations\RestApi;

use Lomkit\Access\Access;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;

/** @internal */
final readonly class ResourceChecks
{
    public function check(ResourceCheckContext $context): void
    {
        Access::controlFor($context->resource::modelClass());

        (new SearchChecks)->check($context);
    }
}
