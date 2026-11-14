<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class UntypedAbilityHookException extends LogicException
{
    public static function for(Perimeter $perimeter): self
    {
        return new self(sprintf(
            'The [%s] perimeter overrides applyAllowedCallback() without typing its second parameter Ability, so a comparison with a string would never match. Declare it as Ability $ability and compare with $ability->is(\'view\').',
            $perimeter::class,
        ));
    }
}
