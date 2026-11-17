<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

class BrandPerimeter extends Perimeter
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query
            ->where('author_id', $context->reader()->getKey())
            ->where('brand_id', $context->decisionContext()['brand'] ?? 0);
    }
}
