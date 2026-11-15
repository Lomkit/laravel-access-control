<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class UnresolvablePolicy implements Problem
{
    public function __construct(
        private string $model,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::UnresolvablePolicy;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The policy of the [%s] model throws when the Gate resolves it: %s', $this->model, $this->reason);
    }
}
