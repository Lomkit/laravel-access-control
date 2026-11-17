<?php

namespace Lomkit\Access\Decisions;

use Closure;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;
use Lomkit\Access\Exceptions\RowContextSwitchException;

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

    /** @param  Closure(): TResult  $decide */
    public function __construct(
        private readonly Closure $decide,
        private readonly DecisionContextResolver $resolver,
        private readonly DecisionContext $before,
        private readonly bool $several,
        private readonly int|string $value,
    ) {}

    /**
     * @return TResult
     *
     * @throws RowContextSwitchException When the callback runs twice, or while the decision context is still the one of the request.
     * @throws InvalidDecisionContextException When the decision context resolver returns an invalid context.
     */
    public function decide(): mixed
    {
        if (++$this->calls > 1) {
            throw RowContextSwitchException::calledTwice($this->value);
        }

        if ($this->several && $this->resolver->current()->equals($this->before)) {
            throw RowContextSwitchException::withoutEffect($this->value, $this->before->values());
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

        $after = $this->resolver->current();

        if (! $after->equals($this->before)) {
            throw RowContextSwitchException::leaked($this->value, $this->before->values(), $after->values());
        }

        return $this->results[0];
    }
}
