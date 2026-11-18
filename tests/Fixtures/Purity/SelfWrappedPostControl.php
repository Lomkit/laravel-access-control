<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class SelfWrappedPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = Config::get('posts.title');
        $wrapper = null;
        $wrapper = WrappingPerimeter::new()->wrapping(
            ClientPerimeter::new()
                ->allowed(function (User $user) use (&$wrapper): bool {
                    return $wrapper instanceof WrappingPerimeter;
                })
                ->query(fn (Builder $query) => $query->where('title', $title)),
        );

        return [$wrapper];
    }
}
