<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;

final class WrappingPerimeter extends Perimeter
{
    private Perimeter|Levels|null $wrapped = null;

    public function wrapping(Perimeter|Levels $wrapped): self
    {
        $perimeter = clone $this;
        $perimeter->wrapped = $wrapped;

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
        $candidates = $this->wrapped instanceof Levels ? $this->wrapped->all() : array_filter([$this->wrapped]);

        foreach ($candidates as $candidate) {
            if ($candidate->applyAllowedCallback($context)) {
                return $candidate;
            }
        }

        return null;
    }
}
