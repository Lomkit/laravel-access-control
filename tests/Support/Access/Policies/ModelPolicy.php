<?php

namespace Lomkit\Access\Tests\Support\Access\Policies;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\PoliciesControlled;
use Lomkit\Access\Tests\Support\Access\Controls\ModelControl;

class ModelPolicy
{
    use PoliciesControlled;

    /**
     * Return the control instance string.
     *
     * @return class-string<Control>
     */
    public function getControl(): string
    {
        return ModelControl::class;
    }
}
