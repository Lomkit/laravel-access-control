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
     * Returns the fully-qualified model class name associated with the policy.
     *
     * This string is used to determine the corresponding control instance for access checks.
     *
     * @return class-string<Model> The model's class name.
     */
    protected function getModel(): string
    {
        return $this->model;
    }

    /**
     * Retrieves the control instance for the policy's model.
     *
     * This method returns a Control instance configured for the model defined in the policy.
     * The returned instance is used to enforce access permissions for various actions on the model.
     *
     * @return Control The control instance associated with the current model.
     */
    protected function getControl(): Control
    {
        return Control::controlForModel($this->getModel());
    }

    /**
     * Check if the given user has permission to view any instances of the model.
     *
     * Delegates the permission evaluation to the Control instance by passing the user, the
     * current method name, and a new instance of the model.
     *
     * @param Model $user The user for which permission is being verified.
     * @return bool True if the user is authorized to view any model instance; false otherwise.
     */
    public function viewAny(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Check if the user is allowed to view the specified model instance.
     *
     * Delegates the permission check to the control instance, which evaluates access based on the user's
     * permissions for the view action on the given model.
     *
     * @param Model $user The user attempting to view the model.
     * @param Model $model The model instance to check access for.
     * @return bool True if the user is permitted to view the model; otherwise, false.
     */
    public function view(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the given user is authorized to create a new instance of the model.
     *
     * Evaluates permissions by invoking the control mechanism with a new instance of the model.
     *
     * @param Model $user The user attempting to create a model instance.
     * @return bool True if the user is permitted to create the model instance, false otherwise.
     */
    public function create(Model $user)
    {
        return $this->getControl()->should($user, __FUNCTION__, new ($this->getModel()));
    }

    /**
     * Determines if the user is authorized to update the specified model instance.
     *
     * Delegates the permission check to the Control instance, which evaluates the update action for the given user and model.
     *
     * @param Model $user The user attempting the update.
     * @param Model $model The model instance that is the target of the update.
     * @return bool True if the user has update permissions; otherwise, false.
     */
    public function update(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }

    /**
     * Determines if the user is authorized to delete the specified model instance.
     *
     * Delegates the deletion permission check to the control instance, which evaluates the user's rights against the provided model.
     *
     * @param Model $user The user attempting the deletion.
     * @param Model $model The model instance subject to deletion.
     * @return bool True if deletion is permitted; false otherwise.
     */
    public function delete(Model $user, Model $model)
    {
        return $this->getControl()->should($user, __FUNCTION__, $model);
    }
}
