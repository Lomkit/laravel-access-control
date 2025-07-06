<?php

namespace Lomkit\Access\Tests\Support\Policies;

use Lomkit\Access\Policies\ControlledPolicy;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;
use Lomkit\Access\Tests\Support\Models\Model;

class ModelPolicy extends ControlledPolicy
{
    protected string $control = ModelControl::class;
}
