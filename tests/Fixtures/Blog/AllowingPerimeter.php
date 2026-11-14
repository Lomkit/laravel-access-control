<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\Perimeter;

class AllowingPerimeter extends Perimeter
{
    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return true;
    }
}
