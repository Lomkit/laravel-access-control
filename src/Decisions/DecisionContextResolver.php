<?php

namespace Lomkit\Access\Decisions;

use Closure;
use Lomkit\Access\Exceptions\InvalidDecisionContextException;

/** @internal */
final class DecisionContextResolver
{
    private ?Closure $resolver = null;

    public function use(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /** @throws InvalidDecisionContextException When the resolver returns something else than an array of scalar values keyed by name. */
    public function current(): DecisionContext
    {
        if ($this->resolver === null) {
            return DecisionContext::none();
        }

        return DecisionContext::of(($this->resolver)());
    }
}
