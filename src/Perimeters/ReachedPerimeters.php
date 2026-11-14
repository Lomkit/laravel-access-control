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

    public function includesDraft(Model $draft, AccessContext $context): bool
    {
        if ($this->includesInMemory($draft, $context)) {
            return true;
        }

        foreach ($this->perimeters as $perimeter) {
            if ($perimeter instanceof ThroughParent && $perimeter->admitsDraft($draft, $context)) {
                return true;
            }
        }

        return false;
    }

    public function fingerprint(AccessContext $context): string
    {
        return self::positions($this->perimeters, $context);
    }

    /** @param  array<string, Perimeter>  $perimeters */
    public static function positions(array $perimeters, AccessContext $context): string
    {
        $positions = [];

        foreach ($perimeters as $position => $perimeter) {
            $positions[] = $perimeter instanceof ThroughParent ? "{$position}({$perimeter->fingerprint($context)})" : $position;
        }

        return implode(',', $positions);
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
