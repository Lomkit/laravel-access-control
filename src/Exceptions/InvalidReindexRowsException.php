<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Perimeters\Perimeter;

final class InvalidReindexRowsException extends LogicException
{
    public static function parameter(Perimeter $perimeter, string $model): self
    {
        return new self(sprintf(
            'The rows callback of the [%s] perimeter does not accept a [%s], the model it reindexes for. Type its parameter with that model, such as fn (Project $project) => $project->posts().',
            $perimeter::class,
            $model,
        ));
    }

    public static function returned(string $keys, string $model, string $returned): self
    {
        return new self(sprintf(
            'The rows callback of the [%s] search keys returned [%s] for a [%s]. It must return a query or a relation of the controlled model, such as $project->posts(), so that its rows can be reindexed in chunks.',
            $keys,
            $returned,
            $model,
        ));
    }

    public static function anotherModel(string $keys, string $controlled, string $returned): self
    {
        return new self(sprintf(
            'The rows callback of the [%s] search keys returned a query of [%s] instead of [%s]. Return the rows of the controlled model whose documents carry the keys.',
            $keys,
            $returned,
            $controlled,
        ));
    }
}
