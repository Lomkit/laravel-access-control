<?php

namespace Lomkit\Access\Decisions;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Container;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Access\Exceptions\InvalidRowContextValuesException;
use Lomkit\Access\Exceptions\MissingRowContextException;
use Lomkit\Access\Exceptions\RowContextSwitchException;

/** @internal */
#[Singleton]
final class RowContextSwitch
{
    private ?Closure $values = null;

    private ?Closure $within = null;

    public static function current(): self
    {
        return Container::getInstance()->make(self::class);
    }

    public function use(Closure $values, Closure $within): void
    {
        $this->values = $values;
        $this->within = $within;
    }

    public function isDeclared(): bool
    {
        return $this->values !== null && $this->within !== null;
    }

    /**
     * @throws MissingRowContextException When the application declares no row context.
     * @throws InvalidRowContextValuesException When the values are not a list of ints and non-empty strings.
     */
    public function values(): RowContextValues
    {
        $values = $this->values ?? throw MissingRowContextException::undeclared();

        return RowContextValues::of($values());
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $decide
     * @return TResult
     *
     * @throws MissingRowContextException When the application declares no row context.
     * @throws RowContextSwitchException When the switch does not run the callback once, has no effect on the decision context, or does not restore it.
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     */
    public function within(RowContextValues $values, int|string $value, Closure $decide): mixed
    {
        $within = $this->within ?? throw MissingRowContextException::undeclared();
        $resolver = Container::getInstance()->make(DecisionContextResolver::class);
        $call = new RowContextCall($decide, $resolver, $resolver->current(), $values->hasSeveral(), $value);

        $within($value, $call->decide(...));

        return $call->result();
    }
}
