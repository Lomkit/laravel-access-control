<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class CollectedPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = Config::get('posts.title');

        return [
            AnyOfPerimeter::new()->holding(collect([
                ClientPerimeter::new()
                    ->allowed(fn (User $user): bool => true)
                    ->query(fn (Builder $query) => $query->where('title', $title)),
            ])),
        ];
    }
}
