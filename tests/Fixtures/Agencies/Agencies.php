<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Agencies implements Fixture
{
    #[\Override]
    public function migrations(): string
    {
        return __DIR__.'/migrations';
    }

    #[\Override]
    public function controls(): array
    {
        return [];
    }
}
