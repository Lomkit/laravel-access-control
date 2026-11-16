<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Drafts;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class SqlWorkspaceBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('workspace_id', $context->reader()->getAttribute('workspace_id'));
    }
}
