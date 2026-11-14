<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class ImpurePerimetersException extends LogicException
{
    public static function for(Control $control): self
    {
        return new self(sprintf(
            'The perimeters() or boundary() method of the [%s] control built a different declaration twice. The control keeps the perimeters and the boundary it builds first for the whole process, a queue worker or an Octane worker included, so perimeters() and boundary() must not read the signed-in user, the request, the tenant or the clock while they build them. Read them inside the callbacks, from the reader they receive.',
            $control::class,
        ));
    }
}
