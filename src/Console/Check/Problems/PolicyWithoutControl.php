<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class PolicyWithoutControl implements Problem
{
    public function __construct(
        private string $model,
        private string $policy,
        private string $control,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::PolicyWithoutControl;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->policy;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] policy of the [%s] model extends ControlledPolicy without a $control, so viewAny() and create() without a draft throw MissingPolicyControlException. Declare protected string $control = %s::class, or remove the policy and call Access::registerPolicies().', $this->policy, $this->model, class_basename($this->control));
    }
}
