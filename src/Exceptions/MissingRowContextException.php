<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class MissingRowContextException extends LogicException
{
    public static function for(Control $control, string $relation, string $column): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control through the [%s] relation is decidedInRowContext(\'%s\'), but the application declares no row context. Call Access::rowContextUsing(values: ..., within: ...) in the boot() method of a service provider: values returns the values of the request, such as its brands, and within runs a callback while the application acts in one of them.',
            $control::class,
            $relation,
            $column,
        ));
    }

    public static function undeclared(): self
    {
        return new self('A ThroughParent perimeter decides its parent in the row context, but the application declares no row context. Call Access::rowContextUsing(values: ..., within: ...) in the boot() method of a service provider, before the first decision.');
    }
}
