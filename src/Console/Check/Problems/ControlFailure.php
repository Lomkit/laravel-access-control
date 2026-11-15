<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class ControlFailure implements Problem
{
    public function __construct(
        private string $control,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::ControlFailure;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->control;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] control throws while it is built or verified: %s', $this->control, $this->reason);
    }
}
