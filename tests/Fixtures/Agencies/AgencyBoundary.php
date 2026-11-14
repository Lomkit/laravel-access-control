<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class AgencyBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $reader = $context->reader();

        $query->where('agency_id', $reader instanceof Member ? $reader->agency_id : 0);

        if (! $context->ability()->is('view')) {
            $query->whereNull('closed_at');
        }
    }
}
