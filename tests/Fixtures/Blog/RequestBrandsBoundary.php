<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class RequestBrandsBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $brands = $context->decisionContext()['brands'] ?? null;

        $query->whereIn('workspace_id', is_string($brands) ? array_map(intval(...), explode(',', $brands)) : []);
    }
}
