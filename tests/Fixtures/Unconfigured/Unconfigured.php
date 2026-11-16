<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Unconfigured implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }
}
