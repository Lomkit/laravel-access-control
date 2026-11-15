<?php

namespace Lomkit\Access\Console\Check\Problems;

use Lomkit\Access\Exceptions\PolicyControlMismatchException;

/** @internal */
final readonly class PolicyForAnotherControl implements Problem
{
    public function __construct(
        private string $model,
        private string $policy,
        private string $policyControl,
        private string $modelControl,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::PolicyForAnotherControl;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->policy;
    }

    #[\Override]
    public function message(): string
    {
        return PolicyControlMismatchException::for($this->policy, $this->policyControl, $this->model, $this->modelControl)->getMessage();
    }
}
