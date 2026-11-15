<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class UnsupportedSearchEngine implements Problem
{
    public function __construct(
        private string $model,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::UnsupportedSearchEngine;
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
