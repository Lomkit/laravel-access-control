<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class SharedMorphClassException extends LogicException
{
    public static function forIndex(string $model, string $morph, string $named): self
    {
        return new self(sprintf(
            'The [%s] model returns the morph class [%s], which names the [%s] model, so the values of an index of your own for these two models would match each other\'s documents. Give the [%s] model a morph class of its own, such as its own alias in Relation::morphMap().',
            $model,
            $morph,
            $named,
            $model,
        ));
    }
}
