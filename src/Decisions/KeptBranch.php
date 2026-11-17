<?php

namespace Lomkit\Access\Decisions;

/**
 * @internal
 *
 * @template TAnswer of bool|string
 */
final readonly class KeptBranch
{
    /** @param  TAnswer  $answer */
    public function __construct(
        private object $perimeter,
        private bool|string $answer,
    ) {}

    /** @return TAnswer|null */
    public function for(object $perimeter): bool|string|null
    {
        return $perimeter === $this->perimeter ? $this->answer : null;
    }
}
