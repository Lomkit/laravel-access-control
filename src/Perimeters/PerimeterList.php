<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\AccessContext;

final readonly class PerimeterList
{
    /** @var list<Perimeter|Levels> */
    private array $entries;

    /** @param  array<Perimeter|Levels>  $entries */
    public function __construct(array $entries)
    {
        $this->entries = array_values($entries);
    }

    public function reachedBy(AccessContext $context): ReachedPerimeters
    {
        $reached = [];

        foreach ($this->entries as $entry) {
            $perimeter = $this->reachedIn($entry, $context);

            if ($perimeter === null) {
                continue;
            }

            if ($perimeter->isUnrestricted()) {
                return ReachedPerimeters::of([$perimeter]);
            }

            $reached[] = $perimeter;
        }

        return ReachedPerimeters::of($reached);
    }

    /** @return list<Levels> */
    public function levels(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (Perimeter|Levels $entry): bool => $entry instanceof Levels,
        ));
    }

    public function matches(self $other): bool
    {
        if (count($this->entries) !== count($other->entries)) {
            return false;
        }

        foreach ($this->entries as $index => $entry) {
            if (! $this->sameEntry($entry, $other->entries[$index])) {
                return false;
            }
        }

        return true;
    }

    private function reachedIn(Perimeter|Levels $entry, AccessContext $context): ?Perimeter
    {
        if ($entry instanceof Levels) {
            return $entry->reachedBy($context);
        }

        return $entry->applyAllowedCallback($context) ? $entry : null;
    }

    private function sameEntry(Perimeter|Levels $entry, Perimeter|Levels $other): bool
    {
        if ($entry instanceof Levels) {
            return $other instanceof Levels && $entry->matches($other);
        }

        return $other instanceof Perimeter && $entry->shape()->matches($other->shape());
    }
}
