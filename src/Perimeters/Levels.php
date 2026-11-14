<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\EmptyLevelsException;
use Lomkit\Access\Exceptions\NestedLevelsException;

final readonly class Levels
{
    /** @param  non-empty-list<Perimeter>  $levels */
    private function __construct(private array $levels) {}

    /**
     * @throws EmptyLevelsException When the group has no level.
     * @throws NestedLevelsException When a level is itself a group.
     */
    public static function of(Perimeter|Levels ...$levels): self
    {
        $perimeters = [];

        foreach (array_values($levels) as $position => $level) {
            if ($level instanceof self) {
                throw NestedLevelsException::at($position + 1);
            }

            $perimeters[] = $level;
        }

        if ($perimeters === []) {
            throw EmptyLevelsException::make();
        }

        return new self($perimeters);
    }

    /** @internal */
    public function reachedBy(AccessContext $context): ?Perimeter
    {
        foreach ($this->levels as $level) {
            if ($level->applyAllowedCallback($context)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * @internal
     *
     * @return non-empty-list<Perimeter>
     */
    public function all(): array
    {
        return $this->levels;
    }

    /** @internal */
    public function matches(self $other): bool
    {
        return (new PerimeterList($this->levels))->matches(new PerimeterList($other->levels));
    }
}
