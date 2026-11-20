<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Index implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }
}
