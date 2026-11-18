<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Perimeters\Perimeter;

final class TitlePerimeter extends Perimeter
{
    private string $title = '';

    public function titled(string $title): self
    {
        $perimeter = clone $this;
        $perimeter->title = $title;

        return $perimeter;
    }

    /** @param  Builder<covariant Model>  $query */
    public function scopeTitle(Builder $query): void
    {
        $query->where('title', $this->title);
    }
}
