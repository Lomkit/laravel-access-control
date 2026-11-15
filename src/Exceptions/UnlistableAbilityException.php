<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Ability;
use Lomkit\Access\Controls\Control;

final class UnlistableAbilityException extends LogicException
{
    public static function for(Control $control, Ability $ability): self
    {
        return new self(sprintf(
            'The [%s] control cannot list the [%s] rows of [%s]: the policy decides [%s] only on a draft, in memory, so a list built from the query() callbacks would contradict the Gate. Authorize each creation with can(\'create\', [%s::class, $draft]) instead of controlled(\'create\').',
            $control::class,
            $ability,
            $control->getModel(),
            $ability,
            class_basename($control->getModel()),
        ));
    }
}
