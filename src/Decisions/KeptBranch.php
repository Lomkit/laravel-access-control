<?php

namespace Lomkit\Access\Decisions;

/** @internal */
final readonly class KeptBranch
{
    public function __construct(
        private object $perimeter,
        private bool $reached,
    ) {}

    public function for(object $perimeter): ?bool
    {
        return $perimeter === $this->perimeter ? $this->reached : null;
    }
}
