<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class SharedChildPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $title = Config::get('posts.title');
        $owner = Config::get('posts.owner');
        $node = ClientPerimeter::new()
            ->allowed(fn (User $user): bool => $owner instanceof User)
            ->query(fn (Builder $query) => $query->where('title', $title));

        for ($depth = 0; $depth < Config::integer('posts.depth'); $depth++) {
            $node = AnyOfPerimeter::new()->holding([$node, $node]);
        }

        return [$node];
    }
}
