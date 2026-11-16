<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

readonly class BaseSecret
{
    public function __construct(private string $secret) {}
}
