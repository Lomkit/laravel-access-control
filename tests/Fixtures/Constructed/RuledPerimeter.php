<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Purity\TitleRule;

final class RuledPerimeter extends Perimeter
{
    public function __construct(public readonly TitleRule $rule) {}
}
