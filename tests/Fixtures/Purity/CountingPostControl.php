<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class CountingPostControl extends PostControl
{
    public int $builds = 0;

    #[\Override]
    protected function perimeters(): array
    {
        $build = ++$this->builds;

        return [
            OwnPerimeter::new()
                ->allowed(fn (User $user): bool => $build > 0)
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                }),
        ];
    }
}
