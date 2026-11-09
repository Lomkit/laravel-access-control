<?php

namespace Lomkit\Access\Tests\Support\Policies;

use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Support\Access\Controls\CountingModelControl;

class CountingModelPolicy extends ControlledPolicy
{
    protected string $control = CountingModelControl::class;
}
