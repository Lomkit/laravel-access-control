<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

final readonly class LinkedRule
{
    public LinkedRule $self;

    public function __construct(
        public string $name,
        public ?LinkedRule $next = null,
    ) {
        $this->self = $this;
    }
}
