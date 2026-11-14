<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class RequestBoundPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = request()->query('title');

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query) use ($title): void {
                    $query->where('title', $title);
                }),
        ];
    }
}
