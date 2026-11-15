<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class InvalidControlledBy implements Problem
{
    public function __construct(
        private string $model,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::InvalidControlledBy;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return $this->reason;
    }
}
