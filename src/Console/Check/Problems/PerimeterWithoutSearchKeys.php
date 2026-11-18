<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class PerimeterWithoutSearchKeys implements PerimeterProblem
{
    public function __construct(
        private string $control,
        private string $position,
        private string $perimeter,
        private string $model,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::PerimeterWithoutSearchKeys;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->control;
    }

    #[\Override]
    public function position(): string
    {
        return $this->position;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] perimeter at %s of the [%s] control declares no search keys, so a reader that only this perimeter reaches finds none of its [%s] rows in a controlled text search. Declare searchKeys() on the perimeter, or unrestricted() if it reaches every row.', $this->perimeter, $this->position, $this->control, $this->model);
    }
}
