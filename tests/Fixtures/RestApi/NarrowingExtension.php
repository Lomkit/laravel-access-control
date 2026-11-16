<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;

class NarrowingExtension extends AccessControlExtension
{
    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        $reader = $context->user instanceof Model ? $context->user : null;

        $context->query
            ->controlledFor($reader)
            ->where(fn (Builder $narrowed) => $narrowed->controlledFor($reader, 'update'));
    }
}
