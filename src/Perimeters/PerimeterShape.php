<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\Search\SearchKeys;

/** @internal */
final readonly class PerimeterShape
{
    /** @param  array<string, ClosureShape>  $callbacks */
    public function __construct(
        private string $perimeter,
        private bool $unrestricted,
        private array $callbacks,
        private ?SearchKeys $searchKeys,
        private PerimeterState $state,
    ) {}

    public function matches(self $other): bool
    {
        if ($this->perimeter !== $other->perimeter || $this->unrestricted !== $other->unrestricted) {
            return false;
        }

        if (array_keys($this->callbacks) !== array_keys($other->callbacks) || ! $this->sameSearchKeys($other->searchKeys) || ! $this->state->matches($other->state)) {
            return false;
        }

        foreach ($this->callbacks as $hook => $callback) {
            if (! $callback->matches($other->callbacks[$hook])) {
                return false;
            }
        }

        return true;
    }

    private function sameSearchKeys(?SearchKeys $other): bool
    {
        if ($this->searchKeys === null || $other === null) {
            return $this->searchKeys === $other;
        }

        return $this->searchKeys->matches($other);
    }
}
