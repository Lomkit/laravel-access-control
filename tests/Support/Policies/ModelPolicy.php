<?php

namespace Lomkit\Access\Tests\Support\Policies;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Policies\ControlledPolicy;

class ModelPolicy extends ControlledPolicy
{
    protected string $model = Model::class;
}