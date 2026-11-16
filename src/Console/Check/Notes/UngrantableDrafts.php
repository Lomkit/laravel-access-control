<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
final readonly class UngrantableDrafts implements Note
{
    public function __construct(
        private string $control,
        private string $model,
    ) {}

    #[\Override]
    public function kind(): NoteKind
    {
        return NoteKind::UngrantableDrafts;
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
            'No perimeter of the [%s] control can grant a draft: none has a should() callback or an includes() hook, none is unrestricted, and no ThroughParent delegates create. Every create with a draft of a [%s] row is refused with outside_rows, and laravel-rest-api passes a draft for each row it creates. Add a should() callback to the perimeters through which readers create these rows, unless the model is read-only.',
            $this->control,
            $this->model,
        );
    }
}
