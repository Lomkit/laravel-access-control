<?php

namespace Lomkit\Access\Controls;

use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Perimeters\ReachedPerimeters;

final readonly class ControlDeclaration
{
    public function __construct(
        private PerimeterList $perimeters,
        private Boundary $boundary,
    ) {}

    public function reachedBy(AccessContext $context): ReachedPerimeters
    {
        if (! $this->boundary->applyAdmits($context)) {
            return ReachedPerimeters::none();
        }

        return $this->perimeters->reachedBy($context);
    }

    public function perimeters(): PerimeterList
    {
        return $this->perimeters;
    }

    public function boundary(): Boundary
    {
        return $this->boundary;
    }

    public function matches(self $other): bool
    {
        return $this->perimeters->matches($other->perimeters) && $this->boundary->matches($other->boundary);
    }
}
