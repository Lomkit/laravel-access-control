<?php

namespace Lomkit\Access;

use Illuminate\Support\Facades\App;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Support\Models\Model;

trait PoliciesControlled
{
    /**
     * Return the control instance string
     *
     * @return class-string<Control>
     */
    public function getControl():string
    {
        return '';
    }

    /**
     * Return a new control instance
     *
     * @return Control
     */
    public function newControl():Control
    {
        return App::make($this->getControl());
    }

    /**
     * Determine if any model can be viewed by the user.
     */
    public function viewAny(Model $user)
    {
        foreach ($this->perimeters->getPerimeters() as $perimeter) {
            if ($this->should($perimeter)) {
                return $this->query($perimeter, $query);
            }
        }
        return false;
    }

    /**
     * Determine if the given model can be viewed by the user.
     */
    public function view(Model $user, Model $model)
    {
        return true;
    }

    /**
     * Determine if the model can be created by the user.
     */
    public function create(Model $user)
    {
        return true;
    }

    /**
     * Determine if the given model can be updated by the user.
     */
    public function update(Model $user, Model $model)
    {
        return true;
    }

    /**
     * Determine if the given model can be deleted by the user.
     */
    public function delete(Model $user, Model $model)
    {
        return true;
    }
}