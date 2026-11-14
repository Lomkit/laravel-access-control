<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;

class ConfiguredPostControl extends PostControl
{
    use ConfiguresPerimeters;

    /** @return list<Perimeter> */
    public function applicableTo(Model $reader, Ability $ability): array
    {
        return $this->applicablePerimeters($reader, $ability);
    }
}
