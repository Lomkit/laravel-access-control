<?php

namespace Lomkit\Access\Tests\Fixtures\Links;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Links implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }
}
