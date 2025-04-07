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
     * Returns the fully qualified model class name associated with this policy.
     *
     * This method retrieves the model string stored in the policy, representing the full class name of the target model.
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
     * This method uses the model class provided by getModel() to obtain a Control instance
     * that handles permissions for model operations.
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
     * This method uses the control instance to assess whether the provided user has
     * permission to view any model instance. It performs this check by delegating to the
     * control's `should()` method with the action name "viewAny" and a freshly instantiated
     * model.
     *
     * @param Model $user The user for which the permission check is performed.
     *
     * @return bool True if the user is authorized to view any instances, false otherwise.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Checks whether a specific model instance is viewable by the given user.
     *
     * This method delegates the permission check to the Control instance using the current function name to determine
     * the 'view' action associated with the provided model instance.
     *
     * @param Model $user  The user whose permission to view the model is being evaluated.
     * @param Model $model The model instance for which view permission is checked.
     *
     * @return bool True if the user is authorized to view the model instance, false otherwise.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Checks if the given user has permission to create a new instance of the model.
     *
     * This method obtains a control instance via getControl() and uses it to determine
     * whether the provided user is authorized to create a model instance. It does so by
     * invoking the should() method with the user, the action name ('create'), and a newly
     * instantiated model based on the class name returned by getModel().
     *
     * @param Model $user The user whose permission to create the model is being verified.
     *
     * @return bool True if the user is allowed to create a new model instance, false otherwise.
     */
    public function create(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determines whether the user is authorized to update the specified model instance.
     *
     * This method delegates the permission check to the Control instance by invoking its
     * should() method with the current action ('update') and the given model instance.
     *
     * @param Model $user  The user attempting to perform the update.
     * @param Model $model The model instance targeted for update.
     *
     * @return bool True if the update action is permitted, false otherwise.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the specified user is authorized to delete the given model instance.
     *
     * This method delegates the permission check to the control layer, which evaluates
     * the 'delete' action for the provided user and model.
     *
     * @param Model $user  The user attempting the deletion.
     * @param Model $model The model instance to be deleted.
     *
     * @return bool True if deletion is permitted, false otherwise.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }
}
