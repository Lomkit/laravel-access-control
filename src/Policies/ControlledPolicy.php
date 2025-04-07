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
     * Retrieves the fully qualified model class name associated with the policy.
     *
     * @return class-string<Model> The model class name.
     */
    protected function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retrieves the control instance associated with the current model.
     *
     * This method uses the model name provided by getModel() to obtain and return the corresponding
     * Control instance via Control::controlForModel().
     *
     * @return Control The control instance configured for the current model.
     */
    protected function getControl(): Control
    {
        return Control::controlForModel($this->getModel());
    }

    /**
     * Determine if the given user is authorized to view any instances of the model.
     *
     * This method instantiates a new model using the associated model class and delegates the permission
     * check to the Control instance. It returns true if the user has permission to view any model instances,
     * and false otherwise.
     *
     * @param Model $user The user instance whose access is being verified.
     *
     * @return bool True if the user is allowed to view any model instances, false otherwise.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Checks if the specified user is authorized to view the given model instance.
     *
     * Delegates the permission check to the associated Control instance, which evaluates
     * whether the user has the necessary access rights to view the model.
     *
     * @param Model $user  The user attempting to view the model.
     * @param Model $model The model instance to be viewed.
     *
     * @return bool True if the user is permitted to view the model, false otherwise.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determine if the user is allowed to create a new instance of the model.
     *
     * This method checks whether the given user has permission to create a new instance
     * of the model by instantiating a new object based on the model class and passing it,
     * along with the user and the action identifier ("create"), to the control's permission check.
     *
     * @param Model $user The user attempting to create a new model instance.
     *
     * @return bool True if the user is permitted to create the model, false otherwise.
     */
    public function create(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Checks if the specified user is permitted to update the given model instance.
     *
     * This method delegates the authorization check to the control mechanism associated with the model,
     * determining if the update operation should be allowed based on the user's context.
     *
     * @param Model $user  The user attempting the update.
     * @param Model $model The model instance to be updated.
     *
     * @return bool True if the user is authorized to perform the update; false otherwise.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Checks if the specified user is authorized to delete the given model instance.
     *
     * This method delegates the deletion permission check to the control associated with the model.
     *
     * @param Model $user  The user attempting to delete the model.
     * @param Model $model The model instance to be deleted.
     *
     * @return bool True if the deletion is permitted; otherwise, false.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }
}
