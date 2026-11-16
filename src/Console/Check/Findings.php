<?php

namespace Lomkit\Access\Console\Check;

use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Problems\Problem;

/** @internal */
final class Findings
{
    /** @var list<Problem> */
    private array $problems = [];

    /** @var list<Note> */
    private array $notes = [];

    public function problem(Problem $problem): void
    {
        foreach ($this->problems as $found) {
            if ($found->kind() === $problem->kind() && $found->subject() === $problem->subject() && $found->message() === $problem->message()) {
                return;
            }
        }

        $this->problems[] = $problem;
    }

    public function note(Note $note): void
    {
        $this->notes[] = $note;
    }

    /** @return list<Problem> */
    public function problems(): array
    {
        return $this->problems;
    }

    /** @return list<Note> */
    public function notes(): array
    {
        return $this->notes;
    }
}
