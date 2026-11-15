<?php

namespace Lomkit\Access\Console\Check\Problems;

use Lomkit\Access\Exceptions\DuplicateControlException;

/** @internal */
final readonly class DuplicateControl implements Problem
{
    public function __construct(
        private string $model,
        private string $control,
        private string $otherControl,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::DuplicateControl;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return DuplicateControlException::forModel($this->model, $this->control, $this->otherControl)->getMessage();
    }
}
