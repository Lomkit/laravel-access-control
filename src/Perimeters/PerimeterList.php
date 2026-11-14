<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Search\SearchKeys;

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

        foreach ($this->entries as $index => $entry) {
            foreach ($this->reachedIn($entry, $index, $context) as $position => $perimeter) {
                if ($perimeter->isUnrestricted()) {
                    return ReachedPerimeters::of([$position => $perimeter]);
                }

                $reached[$position] = $perimeter;
            }
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

    /** @return list<SearchKeys> */
    public function searchKeys(): array
    {
        $keys = [];

        foreach ($this->entries as $entry) {
            foreach ($entry instanceof Levels ? $entry->all() : [$entry] as $perimeter) {
                $declared = $perimeter->declaredSearchKeys();

                if ($declared !== null) {
                    $keys[] = $declared;
                }
            }
        }

        return $keys;
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

    /** @return array<string, Perimeter> */
    private function reachedIn(Perimeter|Levels $entry, int $index, AccessContext $context): array
    {
        if (! $entry instanceof Levels) {
            return $entry->applyAllowedCallback($context) ? ["#{$index}" => $entry] : [];
        }

        $reached = [];

        foreach ($entry->reachedBy($context) as $level => $perimeter) {
            $reached["#{$index}.{$level}"] = $perimeter;
        }

        return $reached;
    }

    private function sameEntry(Perimeter|Levels $entry, Perimeter|Levels $other): bool
    {
        if ($entry instanceof Levels) {
            return $other instanceof Levels && $entry->matches($other);
        }

        return $other instanceof Perimeter && $entry->shape()->matches($other->shape());
    }
}
