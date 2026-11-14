<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Lomkit\Access\Policies\ControlledPolicy;

class MislinkedPostPolicy extends ControlledPolicy
{
    protected string $control = UserControl::class;
}
