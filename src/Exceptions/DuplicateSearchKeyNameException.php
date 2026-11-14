<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class DuplicateSearchKeyNameException extends LogicException
{
    public static function in(string $control, string $name): self
    {
        return new self(sprintf(
            'Two perimeters of the [%s] control declare search keys named [%s]. The name prefixes the keys in the index, so two perimeters with the same name would open each other\'s rows: give each perimeter its own name.',
            $control,
            $name,
        ));
    }
}
