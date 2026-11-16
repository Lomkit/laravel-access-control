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
            'The [%s] policy of the [%s] model does not extend ControlledPolicy, so the package cannot check that it names the model\'s control (policy_for_another_control) nor refuse a mismatch at runtime (PolicyControlMismatchException). A base policy that calls Control::decide() can extend ControlledPolicy instead and override its methods; prove the agreement with assertControlAgrees().',
            $this->policy,
            $this->model,
        );
    }
}
