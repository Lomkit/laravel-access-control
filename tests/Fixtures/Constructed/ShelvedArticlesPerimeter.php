<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

final class ShelvedArticlesPerimeter extends Perimeter
{
    private function __construct(
        private readonly Shelf $shelf,
        private readonly bool $writing,
    ) {}

    public static function reading(Shelf $shelf): self
    {
        return new self($shelf, false);
    }

    public static function writing(Shelf $shelf): self
    {
        return new self($shelf, true);
    }

    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return $this->writing || $context->ability()->is('view');
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        return $row->getAttribute('author_id') === $context->reader()->getKey()
            && $row->getAttribute('featured') === $this->shelf->holdsFeatured();
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $query
            ->where('author_id', $context->reader()->getKey())
            ->where('featured', $this->shelf->holdsFeatured());
    }
}
