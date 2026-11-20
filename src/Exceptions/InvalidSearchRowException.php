<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class InvalidSearchRowException extends InvalidArgumentException
{
    public static function notAModel(mixed $row): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received a value of type [%s]. Pass the Eloquent models to index, such as the collection a query returned.',
            get_debug_type($row),
        ));
    }

    public static function ofAnotherModel(Model $first, Model $row): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received rows of the [%s] model and of the [%s] model. Pass the rows of one model at a time: the keys of each model only match the reader keys of that model.',
            $first::class,
            $row::class,
        ));
    }

    public static function unsaved(Model $row): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received an unsaved [%s] row. Pass saved rows: the keys are returned by the key of each row.',
            $row::class,
        ));
    }
}
