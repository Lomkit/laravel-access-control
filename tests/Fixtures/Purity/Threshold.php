<?php

namespace Lomkit\Access\Tests\Fixtures\Purity;

use Illuminate\Database\Eloquent\Model;

final readonly class Threshold
{
    public function __construct(
        public mixed $value,
        public ?Model $owner = null,
    ) {}
}
