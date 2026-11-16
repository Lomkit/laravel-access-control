<?php

namespace Lomkit\Access\Tests\Fixtures\Types;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class VisibleBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        PostRows::visible($query);
    }
}
