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

    public static function unsavedOrDeleted(Model $row): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received an unsaved or deleted [%s] row. Pass saved rows, and delete the documents of a deleted row from your index.',
            $row::class,
        ));
    }

    public static function trashed(Model $row): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received a soft-deleted [%s] row, which controlledFor() never lists. Delete its documents from your index, and write them again when the row is restored.',
            $row::class,
        ));
    }

    public static function unsupportedKey(Model $row, mixed $key): self
    {
        return new self(sprintf(
            'Access::searchKeysOf() received a [%s] row whose key is of type [%s]. The keys are returned by the key of each row, which must be an int or a string.',
            $row::class,
            get_debug_type($key),
        ));
    }
}
