<?php

namespace Lomkit\Access\Decisions;

use Closure;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Access\Exceptions\InvalidRowContextValuesException;
use Lomkit\Access\Exceptions\RowContextSwitchException;
use Throwable;

/**
 * @internal
 *
 * @template TResult
 */
final class RowContextCall
{
    private int $calls = 0;

    /** @var list<TResult> */
    private array $results = [];

    /**
     * @param  Closure(): TResult  $decide
     * @param  Closure(): RowContextValues  $values
     */
    public function __construct(
        private readonly Closure $decide,
        private readonly Closure $values,
        private readonly DecisionContextResolver $resolver,
        private readonly DecisionContext $before,
        private readonly bool $several,
        private readonly int|string $value,
    ) {}

    /**
     * @return TResult
     *
     * @throws RowContextSwitchException When the callback runs twice, while the decision context is still the one it was called from, or while the values are not the switched value alone.
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     * @throws InvalidRowContextValuesException When the values are not a list of ints and non-empty strings.
     */
    public function decide(): mixed
    {
        if (++$this->calls > 1) {
            throw RowContextSwitchException::calledTwice($this->value);
        }

        if ($this->several && $this->resolver->current()->equals($this->before)) {
            throw RowContextSwitchException::withoutEffect($this->value, $this->before->values());
        }

        $seen = ($this->values)();

        if ($seen->hasSeveral() || $seen->match($this->value) === null) {
            throw RowContextSwitchException::otherValues($this->value, $seen->all());
        }

        $result = ($this->decide)();
        $this->results[] = $result;

        return $result;
    }

    /**
     * @return TResult
     *
     * @throws RowContextSwitchException When the callback did not run exactly once, or the decision context was not restored.
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     */
    public function result(): mixed
    {
        if ($this->calls > 1) {
            throw RowContextSwitchException::calledTwice($this->value);
        }

        if ($this->results === []) {
            throw RowContextSwitchException::notCalled($this->value);
        }

        $this->ensureRestored(null);

        return $this->results[0];
    }

    /**
     * @throws RowContextSwitchException When the decision context was not restored.
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     */
    public function ensureRestored(?Throwable $thrown): void
    {
        $after = $this->resolver->current();

        if (! $after->equals($this->before)) {
            throw RowContextSwitchException::leaked($this->value, $this->before->values(), $after->values(), $thrown);
        }
    }
}
