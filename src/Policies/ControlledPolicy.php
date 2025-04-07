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
     * Retrieves the fully-qualified model class name associated with this policy.
     *
     * This method returns the class name of the model for which the policy is defined,
     * facilitating the dynamic instantiation or reference during permission checks.
     *
     * @return string The model's fully-qualified class name.
     */
    protected function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retrieves the Control instance responsible for managing access permissions
     * for the associated model.
     *
     * This method obtains the model's class name from getModel() and uses it to
     * fetch the corresponding Control instance via Control::controlForModel().
     *
     * @return Control The control instance configured for the current model.
     */
    protected function getControl(): Control
    {
        return Control::controlForModel($this->getModel());
    }

    /**
     * Check if the given user has permission to view any instance of the controlled model.
     *
     * This method creates a new instance of the model (using the class returned by getModel())
     * and delegates the permission check to the associated Control instance via its `should` method.
     *
     * @param Model $user The model instance representing the user whose access is being verified.
     * @return bool True if the user is permitted to view any instance of the model, false otherwise.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determines whether the given user is permitted to view the specified model.
     *
     * This method delegates the permission check to the control instance. It uses the method's name as the action identifier when evaluating if the user should be allowed to view the model.
     *
     * @param Model $user The user attempting to view the model.
     * @param Model $model The model instance being accessed.
     * @return bool True if the user has permission to view the model, otherwise false.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Checks if the given user is authorized to create a new instance of the model.
     *
     * This method delegates the permission check to the control instance associated with the model.
     * It creates a new model instance based on the current model class and uses it to evaluate
     * whether the "create" action is permitted for the provided user.
     *
     * @param Model $user The user for whom the create permission is being checked.
     * @return bool True if the user is allowed to create a new model instance; otherwise, false.
     */
    public function create(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determines if the specified user is authorized to update the given model instance.
     *
     * Delegates the permission check to the control instance associated with the model.
     *
     * @param Model $user The user attempting the update.
     * @param Model $model The model instance to update.
     * @return bool True if the update is permitted, false otherwise.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Check if the specified user has permission to delete the given model instance.
     *
     * Delegates to a control instance that determines the authorization by evaluating
     * the delete action for the provided model.
     *
     * @param Model $user  The user for whom the delete permission is evaluated.
     * @param Model $model The model instance to be deleted.
     * @return bool        True if the user is authorized to delete the model, false otherwise.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }
}
