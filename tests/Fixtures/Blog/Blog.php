<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Blog implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function controls(): array
    {
        return [PostControl::class];
    }
}
