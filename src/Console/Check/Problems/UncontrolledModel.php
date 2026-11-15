<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class UncontrolledModel implements Problem
{
    public function __construct(private string $model) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::UncontrolledModel;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] model uses HasControl, but no control is discovered for it, so controlled() and its policy throw MissingControlException. Create one with "php artisan make:control" in a directory the controls are discovered in, or declare it with #[ControlledBy(...)].', $this->model);
    }
}
