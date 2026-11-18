<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

final class AnyOfPerimeter extends Perimeter
{
    /** @var array<int, Perimeter>|Collection<int, Perimeter> */
    private array|Collection $held = [];

    /** @param  array<int, Perimeter>|Collection<int, Perimeter>  $held */
    public function holding(array|Collection $held): self
    {
        $perimeter = clone $this;
        $perimeter->held = $held;

        return $perimeter;
    }

    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return $this->reached($context) instanceof Perimeter;
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $this->reached($context)?->applyQueryCallback($query, $context);
    }

    private function reached(AccessContext $context): ?Perimeter
    {
        foreach ($this->held as $perimeter) {
            if ($perimeter->applyAllowedCallback($context)) {
                return $perimeter;
            }
        }

        return null;
    }
}
