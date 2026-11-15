<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Lomkit\Access\Tests\Fixtures\Blog\PostControl;

class MemoizingPostControl extends PostControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [MemoizingPerimeter::new()];
    }
}
