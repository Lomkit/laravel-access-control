<?php

namespace Lomkit\Access\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Decisions\Draft;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingPolicyControlException;
use Lomkit\Access\Exceptions\PolicyControlMismatchException;

class ControlledPolicy
{
    /** @var class-string<Control> */
    protected string $control;

    public function viewAny(Model $user): Response
    {
        return $this->decideByControl(__FUNCTION__, $user);
    }

    public function view(Model $user, Model $model): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $model);
    }

    public function create(Model $user, ?Model $draft = null): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $draft === null ? null : Draft::of($draft));
    }

    public function update(Model $user, Model $model): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $model);
    }

    public function delete(Model $user, Model $model): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $model);
    }

    public function restore(Model $user, Model $model): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $model);
    }

    public function forceDelete(Model $user, Model $model): Response
    {
        return $this->decideByControl(__FUNCTION__, $user, $model);
    }

    /**
     * @throws MissingPolicyControlException When there is no row and the policy names no control.
     * @throws MissingControlException When no control is registered for the model.
     * @throws PolicyControlMismatchException When the model is controlled by another control than the one of the policy.
     */
    final protected function decideByControl(string $method, Model $user, Model|Draft|null $subject = null): Response
    {
        $control = $this->controlOf($method, $subject);

        return $control->decide($user, $control->abilityFor($method), $subject);
    }

    protected function declaredControl(): ?Control
    {
        return isset($this->control) ? Access::control($this->control) : null;
    }

    private function controlOf(string $method, Model|Draft|null $subject): Control
    {
        $declared = $this->declaredControl();
        $model = match (true) {
            $subject instanceof Draft => $subject->model()::class,
            $subject instanceof Model => $subject::class,
            default => $declared?->getModel() ?? throw MissingPolicyControlException::for(static::class, $method),
        };
        $control = Access::controlFor($model);

        if ($declared !== null && $declared !== $control) {
            throw PolicyControlMismatchException::for(static::class, $declared::class, $model, $control::class);
        }

        return $control;
    }
}
