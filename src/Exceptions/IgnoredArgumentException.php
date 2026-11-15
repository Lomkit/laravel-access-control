<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class IgnoredArgumentException extends LogicException
{
    public static function scope(Model $model, string $scope, int $surplus): self
    {
        return new self(sprintf(
            '%s() on a query of the [%s] model takes a single ability and received %d more argument(s), which it would ignore. List the rows of one ability per query, and decide the other abilities on those rows with withAbilities([...]).',
            $scope,
            $model::class,
            $surplus,
        ));
    }

    public static function levelsNest(string $control): self
    {
        return new self(sprintf(
            'assertLevelsNest() for the [%s] control received an argument after the readers, such as an ability, which it would ignore: it compares the query() of the levels with the view ability. Pass the control and the readers only.',
            $control,
        ));
    }
}
