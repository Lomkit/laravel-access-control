<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class WrappedLevelsPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = Config::get('posts.title');

        return [
            WrappingPerimeter::new()->wrapping(Levels::of(
                OwnPerimeter::new()
                    ->allowed(fn (User $user): bool => false)
                    ->query(function (Builder $query, User $user): void {
                        $query->whereBelongsTo($user, 'author');
                    }),
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(fn (Builder $query) => $query->where('title', $title)),
            )),
        ];
    }
}
