<?php

namespace Lomkit\Access\Tests\Fixtures;

use Lomkit\Access\Controls\Control;

interface Fixture
{
    public function migrations(): string;

    /** @return list<class-string<Control>> */
    public function controls(): array;
}
