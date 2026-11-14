<?php

namespace Lomkit\Access\Perimeters;

final readonly class PerimeterShape
{
    /** @param  array<string, ClosureShape>  $callbacks */
    public function __construct(
        private string $perimeter,
        private bool $unrestricted,
        private array $callbacks,
    ) {}

    public function matches(self $other): bool
    {
        if ($this->perimeter !== $other->perimeter || $this->unrestricted !== $other->unrestricted) {
            return false;
        }

        if (array_keys($this->callbacks) !== array_keys($other->callbacks)) {
            return false;
        }

        foreach ($this->callbacks as $hook => $callback) {
            if (! $callback->matches($other->callbacks[$hook])) {
                return false;
            }
        }

        return true;
    }
}
