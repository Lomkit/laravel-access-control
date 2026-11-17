<?php

namespace Lomkit\Access\Perimeters;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\EmptyLevelsException;
use Lomkit\Access\Exceptions\NestedLevelsException;
use Lomkit\Access\Exceptions\RowContextInLevelsException;

final readonly class Levels
{
    /** @param  non-empty-list<Perimeter>  $levels */
    private function __construct(private array $levels) {}

    /**
     * @throws EmptyLevelsException When the group has no level.
     * @throws NestedLevelsException When a level is itself a group.
     * @throws RowContextInLevelsException When a level is a ThroughParent that decides its parent in the row context.
     */
    public static function of(Perimeter|Levels ...$levels): self
    {
        $perimeters = [];

        foreach (array_values($levels) as $position => $level) {
            if ($level instanceof self) {
                throw NestedLevelsException::at($position + 1);
            }

            $column = $level instanceof ThroughParent ? $level->rowContextColumn() : null;

            if ($column !== null) {
                throw RowContextInLevelsException::at($position + 1, $level->delegation()->relation(), $column);
            }

            $perimeters[] = $level;
        }

        if ($perimeters === []) {
            throw EmptyLevelsException::make();
        }

        return new self($perimeters);
    }

    /**
     * @internal
     *
     * @return array<int, Perimeter>
     */
    public function reachedBy(AccessContext $context): array
    {
        foreach ($this->levels as $position => $level) {
            if ($level->applyAllowedCallback($context)) {
                return [$position => $level];
            }
        }

        return [];
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
