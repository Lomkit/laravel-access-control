<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class UnreadableRelation implements Problem
{
    public function __construct(
        private string $model,
        private string $relation,
        private string $reason,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::UnreadableRelation;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] relation of the [%s] model throws when the package reads it without a database connection, so access:check cannot verify it: %s Fix the [%s::%s()] method.', $this->relation, $this->model, rtrim($this->reason, '.').'.', $this->model, $this->relation);
    }
}
