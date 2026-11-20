<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Search\SearchKeys;

class GuideBrandBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('brand_id', $context->decisionContext()['brand'] ?? 0);
    }

    #[\Override]
    protected function searchKeys(): SearchKeys
    {
        return SearchKeys::of(
            name: 'brand',
            row: fn (Model $row): mixed => $row->getAttribute('brand_id'),
            reader: fn (Model $reader, AccessContext $context): mixed => $context->decisionContext()['brand'] ?? null,
        );
    }
}
