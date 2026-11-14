<?php

namespace Lomkit\Access\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\PolicyControlMismatchException;

class ControlledPolicy
{
    // @TODO: what to do for other methods like attach / restore / force_delete ? It only has view / etc basic methods

    /** @var class-string<Control> */
    protected string $control;

    /**
     * @throws PolicyControlMismatchException When the model of the control is controlled by another control.
     */
    protected function getControl(): Control
    {
        return $this->controlOf(Access::control($this->control)->getModel());
    }

    public function viewAny(Model $user): bool|Response
    {
        $control = $this->getControl();

        return $this->decide($control, $user, __FUNCTION__, new ($control->getModel()));
    }

    public function view(Model $user, Model $model): bool|Response
    {
        return $this->decide($this->controlOf($model::class), $user, __FUNCTION__, $model);
    }

    public function create(Model $user): bool|Response
    {
        $control = $this->getControl();

        return $this->decide($control, $user, __FUNCTION__, new ($control->getModel()));
    }

    public function update(Model $user, Model $model): bool|Response
    {
        return $this->decide($this->controlOf($model::class), $user, __FUNCTION__, $model);
    }

    public function delete(Model $user, Model $model): bool|Response
    {
        return $this->decide($this->controlOf($model::class), $user, __FUNCTION__, $model);
    }

    public function restore(Model $user, Model $model): bool|Response
    {
        return $this->decide($this->controlOf($model::class), $user, __FUNCTION__, $model);
    }

    public function forceDelete(Model $user, Model $model): bool|Response
    {
        return $this->decide($this->controlOf($model::class), $user, __FUNCTION__, $model);
    }

    private function decide(Control $control, Model $user, string $policyMethod, Model $model): bool
    {
        return $control->applies($user, $control->abilityFor($policyMethod), $model);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws PolicyControlMismatchException When the model is controlled by another control.
     */
    private function controlOf(string $model): Control
    {
        $control = Access::control($this->control);
        $registered = Access::controlFor($model);

        if ($registered !== $control) {
            throw PolicyControlMismatchException::for(static::class, $this->control, $model, $registered::class);
        }

        return $control;
    }
}
