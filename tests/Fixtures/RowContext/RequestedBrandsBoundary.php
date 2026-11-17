<?php

namespace Lomkit\Access\Tests\Fixtures\RowContext;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

class RequestedBrandsBoundary extends Boundary
{
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->whereIn('workspace_id', $this->brands());
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        return in_array($row->getAttribute('workspace_id'), $this->brands(), true);
    }

    /** @return list<int> */
    private function brands(): array
    {
        return Container::getInstance()->make(RequestedBrands::class)->all();
    }
}
