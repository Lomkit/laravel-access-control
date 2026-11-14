<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Lomkit\Access\Tests\Fixtures\Fixture;

class Search implements Fixture
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
