<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

final class WrittenArticlesPerimeter extends Perimeter
{
    private ?bool $featured = null;

    public function featured(bool $featured): static
    {
        $perimeter = clone $this;
        $perimeter->featured = $featured;

        return $perimeter;
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        if ($row->getAttribute('author_id') !== $context->reader()->getKey()) {
            return false;
        }

        return $this->featured === null || $row->getAttribute('featured') === $this->featured;
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query->where('author_id', $context->reader()->getKey());

        if ($this->featured !== null) {
            $query->where('featured', $this->featured);
        }
    }
}
