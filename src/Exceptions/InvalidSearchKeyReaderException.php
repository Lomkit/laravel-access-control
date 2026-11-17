<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Perimeters\Perimeter;
use ReflectionParameter;

final class InvalidSearchKeyReaderException extends LogicException
{
    public static function for(Perimeter|Boundary $declarer, string $name, ReflectionParameter $parameter): self
    {
        return new self(sprintf(
            'The [%s] %s declares the [%s] search keys with a reader closure that takes [%s], which the package cannot fill. Declare the reader closure as fn (Model $reader, AccessContext $context), or with the reader alone.',
            $declarer::class,
            $declarer instanceof Perimeter ? 'perimeter' : 'boundary',
            $name,
            self::describe($parameter),
        ));
    }

    private static function describe(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        return $type === null
            ? "\${$parameter->getName()}"
            : "{$type} \${$parameter->getName()}";
    }
}
