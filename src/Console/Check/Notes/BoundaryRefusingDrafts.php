<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
final readonly class BoundaryRefusingDrafts implements Note
{
    public function __construct(
        private string $control,
        private string $boundary,
    ) {}

    #[\Override]
    public function kind(): NoteKind
    {
        return NoteKind::BoundaryRefusingDrafts;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->control;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] boundary of the [%s] control has no in-memory form, so it refuses every draft with outside_boundary, and laravel-rest-api passes a draft for each row it creates. Override includes() on the boundary, or declare it with Boundary::where(), unless the model is read-only.',
            $this->boundary,
            $this->control,
        );
    }
}
