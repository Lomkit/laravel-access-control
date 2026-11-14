<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Threads implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }
}
