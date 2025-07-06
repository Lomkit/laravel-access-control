<?php

namespace Lomkit\Access\Tests\Support\Policies;

use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;

class ModelPolicy extends ControlledPolicy
{
    protected string $control = ModelControl::class;
}
