<?php

namespace Lomkit\Access\Perimeters\Parents;

use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;

/** @internal */
final readonly class ParentStep
{
    public function __construct(
        public string $relation,
        public string $foreignKey,
        public string $ownerKey,
        public Control $control,
        public Ability $ability,
    ) {}
}
