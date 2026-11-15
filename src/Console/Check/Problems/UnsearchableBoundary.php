<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class UnsearchableBoundary implements Problem
{
    public function __construct(
        private string $control,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::UnsearchableBoundary;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->control;
    }

    #[\Override]
    public function message(): string
    {
        return $this->reason;
    }
}
