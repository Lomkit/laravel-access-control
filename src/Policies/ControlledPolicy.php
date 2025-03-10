<?php

namespace Lomkit\Access\Policies;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;

class ControlledPolicy
{
    //@TODO: what to do for other methods like attach ? It only has view / etc basic methods

    /**
     * The model class string.
     *
     * @var string
     */
    protected string $model = '';

    /**
     * Return the control instance string.
     *
     * @return string-class<Model>
     */
    protected function getModel(): string
    {
        return $this->model;
    }

    /**
     * Return the control instance.
     *
     * @return Control
     */
    protected function getControl(): Control
    {
        return Control::controlForModel($this->getModel());
    }

    /**
     * Determine if any model can be viewed by the user.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determine if the given model can be viewed by the user.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determine if the model can be created by the user.
     */
    public function create(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determine if the given model can be updated by the user.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determine if the given model can be deleted by the user.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }
}
