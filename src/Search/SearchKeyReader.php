<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\ParameterType;
use ReflectionFunction;
use ReflectionParameter;

/** @internal */
final readonly class SearchKeyReader
{
    private function __construct(
        private Closure $closure,
        private ParameterType $reader,
        private bool $readsContext,
    ) {}

    public static function of(Closure $closure): self
    {
        $function = new ReflectionFunction($closure);

        return new self($closure, ParameterType::at($function, 0), $function->getNumberOfParameters() > 1);
    }

    public static function withoutContext(Closure $closure): self
    {
        return new self($closure, ParameterType::at(new ReflectionFunction($closure), 0), false);
    }

    public function unfillableParameter(): ?ReflectionParameter
    {
        if (! $this->readsContext) {
            return null;
        }

        $function = new ReflectionFunction($this->closure);
        $parameters = $function->getParameters();

        if (! ParameterType::at($function, 1)->admits(AccessContext::class)) {
            return $parameters[1];
        }

        foreach (array_slice($parameters, 2) as $parameter) {
            if (! $parameter->isOptional()) {
                return $parameter;
            }
        }

        return null;
    }

    public function admits(Model $reader): bool
    {
        return $this->reader->admits($reader);
    }

    public function values(AccessContext $context): mixed
    {
        return $this->readsContext
            ? ($this->closure)($context->reader(), $context)
            : ($this->closure)($context->reader());
    }

    public function matches(self $other): bool
    {
        return ClosureShape::of($this->closure)->matches(ClosureShape::of($other->closure));
    }
}
