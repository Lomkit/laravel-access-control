<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
final readonly class ModelWithoutPolicy implements Note
{
    public function __construct(private string $model) {}

    #[\Override]
    public function kind(): NoteKind
    {
        return NoteKind::ModelWithoutPolicy;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'No policy is registered for the [%s] model, so the Gate denies its abilities: only controlled() and the control itself decide its rows. Call Access::registerPolicies() in a service provider, or give the model a ControlledPolicy.',
            $this->model,
        );
    }
}
