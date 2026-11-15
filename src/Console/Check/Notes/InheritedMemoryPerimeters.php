<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
final readonly class InheritedMemoryPerimeters implements Note
{
    /**
     * @param  list<string>  $perimeters
     * @param  list<string>  $controls
     */
    public function __construct(
        private string $base,
        private array $perimeters,
        private array $controls,
    ) {}

    #[\Override]
    public function kind(): NoteKind
    {
        return NoteKind::InheritedMemoryPerimeters;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->base;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] base control declares perimeters with a should() callback ([%s]), so its %d controls [%s] check them in memory. Override perimeters() in a control whose rows should be checked in SQL.',
            $this->base,
            implode(', ', $this->perimeters),
            count($this->controls),
            implode(', ', $this->controls),
        );
    }
}
