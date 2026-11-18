<?php

namespace Lomkit\Access\Console\Check;

use Lomkit\Access\Console\Check\Inventory\ControlInventory;
use Lomkit\Access\Console\Check\Inventory\DiscoveryInventory;
use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Problems\PositionedProblem;
use Lomkit\Access\Console\Check\Problems\Problem;

/** @internal */
final readonly class CheckReport
{
    /**
     * @param  list<ControlInventory>  $controls
     * @param  list<Problem>  $problems
     * @param  list<Note>  $notes
     */
    public function __construct(
        public DiscoveryInventory $discovery,
        public array $controls,
        public int $relations,
        public array $problems,
        public array $notes,
    ) {}

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'discovery' => $this->discovery->toArray(),
            'controls' => array_map(fn (ControlInventory $control): array => $control->toArray(), $this->controls),
            'pivots' => ['relations' => $this->relations],
            'problems' => array_map(fn (Problem $problem): array => [
                'kind' => $problem->kind()->value,
                'subject' => $problem->subject(),
                'position' => $problem instanceof PositionedProblem ? $problem->position() : null,
                'message' => $problem->message(),
            ], $this->problems),
            'notes' => array_map(fn (Note $note): array => ['kind' => $note->kind()->value, 'subject' => $note->subject(), 'message' => $note->message()], $this->notes),
        ];
    }
}
