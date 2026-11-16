<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

final readonly class NamedSecret extends BaseSecret
{
    public function __construct(
        string $secret,
        public string $name,
    ) {
        parent::__construct($secret);
    }
}
