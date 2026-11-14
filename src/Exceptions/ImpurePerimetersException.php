<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class ImpurePerimetersException extends LogicException
{
    public static function for(Control $control): self
    {
        return new self(sprintf(
            'The perimeters() method of the [%s] control built different perimeters twice. The control keeps the perimeters it builds first for the whole process, a queue worker or an Octane worker included, so perimeters() must not read the signed-in user, the request, the tenant, the database or the clock, nor create objects that its callbacks capture. Read them inside the callbacks, from the reader they receive.',
            $control::class,
        ));
    }
}
