<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class DuplicateSearchKeyNameException extends LogicException
{
    public static function reservedForParents(string $control, string $name): self
    {
        return new self(sprintf(
            'A perimeter of the [%s] control declares search keys named [%s], the name that prefixes the keys its ThroughParent perimeters inherit from their parents. A key of that perimeter could open the rows of a parent: give the perimeter another name.',
            $control,
            $name,
        ));
    }

    public static function in(string $control, string $name): self
    {
        return new self(sprintf(
            'Two perimeters of the [%s] control declare search keys named [%s]. The name prefixes the keys in the index, so two perimeters with the same name would open each other\'s rows: give each perimeter its own name.',
            $control,
            $name,
        ));
    }
}
