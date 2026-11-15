<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

final class MemoizingPerimeter extends Perimeter
{
    /** @var array<string, bool> */
    private array $allowedReaders = [];

    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        $key = (string) $context->reader()->getKey();

        return $this->allowedReaders[$key] ??= true;
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('author_id', $context->reader()->getKey());
    }
}
