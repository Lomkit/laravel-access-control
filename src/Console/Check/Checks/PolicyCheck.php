<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Contracts\Auth\Access\Gate;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Inventory\PolicyInventory;
use Lomkit\Access\Console\Check\Notes\ModelWithoutPolicy;
use Lomkit\Access\Console\Check\Notes\PolicyOutsideControl;
use Lomkit\Access\Console\Check\Problems\PolicyForAnotherControl;
use Lomkit\Access\Console\Check\Problems\PolicyWithoutControl;
use Lomkit\Access\Console\Check\Problems\UnresolvablePolicy;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/** @internal */
final readonly class PolicyCheck
{
    private const string DECLARED_CONTROL = 'declaredControl';

    private const string CONTROL = 'control';

    public function __construct(private Gate $gate) {}

    /**
     * @param  list<Control>  $controls
     * @return array<class-string<Control>, PolicyInventory>
     */
    public function run(Findings $findings, array $controls): array
    {
        $policies = [];

        foreach ($controls as $control) {
            $model = $control->getModel();

            try {
                $policy = $this->gate->getPolicyFor($model);
            } catch (Throwable $exception) {
                $findings->problem(new UnresolvablePolicy($model, $exception->getMessage()));
                $policies[$control::class] = PolicyInventory::unresolvable();

                continue;
            }

            $policies[$control::class] = PolicyInventory::resolved($policy);

            if (! is_object($policy)) {
                $findings->note(new ModelWithoutPolicy($model));
            } elseif (! $policy instanceof ControlledPolicy) {
                $findings->note(new PolicyOutsideControl($model, $policy::class));
            } elseif ((new ReflectionMethod($policy, self::DECLARED_CONTROL))->getDeclaringClass()->getName() === ControlledPolicy::class) {
                $this->checkNamedControl($findings, $policy, $control);
            }
        }

        return $policies;
    }

    private function checkNamedControl(Findings $findings, ControlledPolicy $policy, Control $control): void
    {
        $property = new ReflectionProperty($policy, self::CONTROL);
        $named = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

        if (! is_string($named)) {
            $findings->problem(new PolicyWithoutControl($control->getModel(), $policy::class, $control::class));
        } elseif ($named !== $control::class) {
            $findings->problem(new PolicyForAnotherControl($control->getModel(), $policy::class, $named, $control::class));
        }
    }
}
