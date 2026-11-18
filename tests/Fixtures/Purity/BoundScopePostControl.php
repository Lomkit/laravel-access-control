<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;

class BoundScopePostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        $source = TitlePerimeter::new()->titled(Config::string('posts.title'));

        return [
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query($source->scopeTitle(...)),
        ];
    }
}
