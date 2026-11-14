<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

final readonly class ReachedPerimeters
{
    /** @param  array<string, Perimeter>  $perimeters */
    private function __construct(private array $perimeters) {}

    public static function none(): self
    {
        return new self([]);
    }

    /** @param  array<string, Perimeter>  $perimeters */
    public static function of(array $perimeters): self
    {
        return new self($perimeters);
    }

    public function isEmpty(): bool
    {
        return $this->perimeters === [];
    }

    public function reachesEveryRow(): bool
    {
        foreach ($this->perimeters as $perimeter) {
            if ($perimeter->isUnrestricted()) {
                return true;
            }
        }

        return false;
    }

    public function includesInMemory(Model $row, AccessContext $context): bool
    {
        foreach ($this->perimeters as $perimeter) {
            if ($perimeter->checksInMemory() && $perimeter->applyShouldCallback($row, $context)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, Perimeter> */
    public function checkedInSql(): array
    {
        return array_filter($this->perimeters, fn (Perimeter $perimeter): bool => ! $perimeter->checksInMemory());
    }

    /** @return list<Perimeter> */
    public function all(): array
    {
        return array_values($this->perimeters);
    }
}
