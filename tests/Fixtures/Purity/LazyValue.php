<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

final readonly class LazyValue
{
    public string $value;

    public function __construct(?string $value)
    {
        if ($value !== null) {
            $this->value = $value;
        }
    }
}
