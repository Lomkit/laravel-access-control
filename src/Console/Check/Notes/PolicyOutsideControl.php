<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
final readonly class PolicyOutsideControl implements Note
{
    public function __construct(
        private string $model,
        private string $policy,
    ) {}

    #[\Override]
    public function kind(): NoteKind
    {
        return NoteKind::PolicyOutsideControl;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->policy;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] policy of the [%s] model does not extend ControlledPolicy, so its decisions follow the control only where it calls it. Extend ControlledPolicy, or remove the policy and call Access::registerPolicies().',
            $this->policy,
            $this->model,
        );
    }
}
