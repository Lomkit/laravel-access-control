<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class ThroughParentCallbackException extends LogicException
{
    public static function for(string $method): self
    {
        return new self(sprintf(
            'A ThroughParent perimeter takes no ->%s(): the control of the parent decides for it, for the same reader. Declare the rule in a separate perimeter of the control, next to the ThroughParent.',
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
