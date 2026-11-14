<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class WorkspaceBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->where('workspace_id', $reader instanceof User ? $reader->current_workspace_id : 0);

        if (! $context->ability()->is('view')) {
            $query->whereNull('archived_at');
        }
    }
}
