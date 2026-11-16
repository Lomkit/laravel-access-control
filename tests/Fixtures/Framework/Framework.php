<?php

namespace Lomkit\Access\Tests\Fixtures\Framework;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Framework implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }
}
