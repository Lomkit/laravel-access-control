<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;

final class TitleBoundary extends Boundary
{
    public function __construct(private readonly mixed $title) {}

    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('title', $this->title);
    }
}
