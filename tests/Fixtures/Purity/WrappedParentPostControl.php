<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Support\Facades\Config;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;

class WrappedParentPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            WrappingPerimeter::new()->wrapping(ThroughParent::belongsTo(Config::string('posts.parent'))),
        ];
    }
}
