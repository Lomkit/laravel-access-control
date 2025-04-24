<?php

namespace Lomkit\Access\Policies;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;

class ControlledPolicy
{
    //@TODO: what to do for other methods like attach / restore / force_delete ? It only has view / etc basic methods

    /**
     * The model class string.
     *
     * @var string
     */
    protected string $model = '';

    /**
     * Returns the fully qualified model class name associated with this policy.
     *
     * @return string The fully qualified class name of the model.
     */
    protected function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retrieves the control instance associated with the current model.
     *
     * @return Control The control instance for the current model.
     */
    protected function getControl(): Control
    {
        return Control::controlForModel($this->getModel());
    }

    /**
     * Determine if the user is authorized to view any instances of the model.
     *
     * @param Model $user The user for which the permission check is performed.
     *
     * @return bool True if the user is authorized to view any instances, false otherwise.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->applies($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Checks whether a specific model instance is viewable by the given user.
     *
     * @param Model $user  The user whose permission to view the model is being evaluated.
     * @param Model $model The model instance for which view permission is checked.
     *
     * @return bool True if the user is authorized to view the model instance, false otherwise.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }

    /**
     * Checks if the given user has permission to create a new instance of the model.
     *
     * @param Model $user The user whose permission to create the model is being verified.
     *
     * @return bool True if the user is allowed to create a new model instance, false otherwise.
     */
    public function create(Model $user)
    {
        return $this->getControl()->applies($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determines whether the user is authorized to update the specified model instance.
     *
     * @param Model $user  The user attempting to perform the update.
     * @param Model $model The model instance targeted for update.
     *
     * @return bool True if the update action is permitted, false otherwise.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the specified user is authorized to delete the given model instance.
     *
     * @param Model $user  The user attempting the deletion.
     * @param Model $model The model instance to be deleted.
     *
     * @return bool True if deletion is permitted, false otherwise.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the specified user is authorized to restore the given model instance.
     *
     * @param Model $user  The user attempting the restoration.
     * @param Model $model The model instance to be restored.
     *
     * @return bool True if restoration is permitted, false otherwise.
     */
    public function restore(Model $user, Model $model)
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the specified user is authorized to force delete the given model instance.
     *
     * @param Model $user  The user attempting the force deletion.
     * @param Model $model The model instance to be force deleted.
     *
     * @return bool True if force deletion is permitted, false otherwise.
     */
    public function forceDelete(Model $user, Model $model)
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }
}
