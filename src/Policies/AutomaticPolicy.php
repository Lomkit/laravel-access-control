<?php

namespace Lomkit\Access\Policies;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;

/** @internal */
final class AutomaticPolicy extends ControlledPolicy
{
    /** @param  class-string<Model>  $model */
    public function __construct(private readonly string $model) {}

    #[\Override]
    protected function declaredControl(): Control
    {
        return Access::controlFor($this->model);
    }
}
