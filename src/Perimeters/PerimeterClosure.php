<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Exceptions\PerimeterCallbackResultException;
use Lomkit\Access\Exceptions\QueryCallbackReturnedAnotherBuilderException;
use ReflectionFunction;

final readonly class PerimeterClosure
{
    private function __construct(
        private Closure $closure,
        private PerimeterHook $hook,
        private ParameterType $reader,
    ) {}

    /** @throws AbilityParameterTypeException If the closure takes the ability in a parameter that does not accept an Ability. */
    public static function of(Closure $closure, PerimeterHook $hook, Perimeter $perimeter): self
    {
        $function = new ReflectionFunction($closure);
        $ability = ParameterType::at($function, $hook->abilityPosition());

        $parameter = $ability->parameter();

        if ($parameter !== null && ! $ability->isTypedFor(Ability::view())) {
            throw AbilityParameterTypeException::forCallback($perimeter, $hook, $parameter);
        }

        return new self($closure, $hook, ParameterType::at($function, $hook->readerPosition()));
    }

    public function admits(Model $reader): bool
    {
        return $this->reader->admits($reader);
    }

    /** @throws PerimeterCallbackResultException If the closure does not return a boolean. */
    public function decide(Perimeter $perimeter, Ability $ability, mixed ...$arguments): bool
    {
        $decision = ($this->closure)(...[...$arguments, $ability]);

        if (! is_bool($decision)) {
            throw PerimeterCallbackResultException::notABoolean($perimeter, $this->hook, $decision);
        }

        return $decision;
    }

    /**
     * @param  Builder<covariant Model>  $query
     *
     * @throws QueryCallbackReturnedAnotherBuilderException If the closure returns another builder.
     */
    public function constrain(Perimeter $perimeter, Ability $ability, Builder $query, Model $reader): void
    {
        $returned = ($this->closure)($query, $reader, $ability);

        if ($returned !== null && $returned !== $query) {
            throw QueryCallbackReturnedAnotherBuilderException::for($perimeter, $this->hook, $query->getModel());
        }
    }

    public function shape(): ClosureShape
    {
        return ClosureShape::of($this->closure);
    }
}
