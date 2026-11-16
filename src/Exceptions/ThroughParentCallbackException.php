<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class ThroughParentCallbackException extends LogicException
{
    public static function for(string $method): self
    {
        return new self(sprintf(
            'A ThroughParent perimeter takes no ->%s(): the control of the parent decides which rows it reaches. To require a rule of the child as well, declare ->allowed() on the ThroughParent; to grant other rows, declare a separate perimeter next to it.',
            $method,
        ));
    }

    public static function construct(): self
    {
        return new self('A ThroughParent perimeter is built with ThroughParent::belongsTo(\'relation\'), which names the parent the rows inherit their access from.');
    }

    public static function unbound(string $relation): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter through the [%s] relation is used outside a control. Return it from the perimeters() of the control of the child model, which binds it to that model.',
            $relation,
        ));
    }
}
