<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

final class JoinInPerimeterException extends LogicException
{
    private const string ADVICE = 'Laravel drops a join added inside the nested where group the rule runs in, so the rule would break the SQL or filter nothing: use whereHas() or an exists subquery instead.';

    public static function forPerimeter(Control $control, Perimeter $perimeter): self
    {
        return new self(sprintf(
            'The [query] callback of the [%s] perimeter in the [%s] control adds a join. %s',
            $perimeter::class,
            $control::class,
            self::ADVICE,
        ));
    }

    public static function forPerimeterOf(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The [query] callback of the [%s] perimeter adds a join to the query of the [%s] model. %s',
            $perimeter::class,
            $model,
            self::ADVICE,
        ));
    }

    public static function forBoundary(Boundary $boundary, string $model): self
    {
        return new self(sprintf(
            'The scope() of the [%s] boundary adds a join to the query of the [%s] model. %s',
            $boundary::class,
            $model,
            self::ADVICE,
        ));
    }
}
