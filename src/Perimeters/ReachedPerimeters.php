<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

final readonly class ReachedPerimeters
{
    /** @param  list<Perimeter>  $perimeters */
    private function __construct(private array $perimeters) {}

    public static function none(): self
    {
        return new self([]);
    }

    /** @param  list<Perimeter>  $perimeters */
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

    public function includes(Model $row, AccessContext $context): bool
    {
        foreach ($this->perimeters as $perimeter) {
            if ($perimeter->applyShouldCallback($row, $context)) {
                return true;
            }
        }

        return false;
    }

    public function includesDraft(Model $draft, AccessContext $context): bool
    {
        foreach ($this->perimeters as $perimeter) {
            if ($perimeter->checksInMemory() && $perimeter->applyShouldCallback($draft, $context)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Perimeter> */
    public function all(): array
    {
        return $this->perimeters;
    }
}
