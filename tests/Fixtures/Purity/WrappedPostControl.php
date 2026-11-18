<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class WrappedPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = Config::get('posts.title');

        return [
            WrappingPerimeter::new()->wrapping(
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(fn (Builder $query) => $query->where('title', $title)),
            ),
        ];
    }
}
