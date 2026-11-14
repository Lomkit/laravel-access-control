<?php

namespace Lomkit\Access\Perimeters;

final readonly class PerimeterList
{
    /** @var list<Perimeter> */
    private array $perimeters;

    /** @param  array<Perimeter>  $perimeters */
    public function __construct(array $perimeters)
    {
        $this->perimeters = array_values($perimeters);
    }

    /** @return list<Perimeter> */
    public function all(): array
    {
        return $this->perimeters;
    }

    public function matches(self $other): bool
    {
        if (count($this->perimeters) !== count($other->perimeters)) {
            return false;
        }

        foreach ($this->perimeters as $index => $perimeter) {
            if (! $perimeter->shape()->matches($other->perimeters[$index]->shape())) {
                return false;
            }
        }

        return true;
    }
}
