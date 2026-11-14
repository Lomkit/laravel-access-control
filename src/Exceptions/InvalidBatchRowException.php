<?php

namespace Lomkit\Access\Exceptions;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lomkit\Access\Controls\Control;

final class InvalidBatchRowException extends InvalidArgumentException
{
    public static function notAModel(mixed $row): self
    {
        return new self(sprintf(
            'A batch of decisions received a value of type [%s]. Pass the Eloquent models to decide, such as the collection a query returned.',
            get_debug_type($row),
        ));
    }

    public static function foreign(Control $control, Model $row): self
    {
        return new self(sprintf(
            'The [%s] control decides rows of the [%s] model, but received a row of the [%s] model. Pass only rows of its model, or use Access::preload(), which groups the rows by model.',
            $control::class,
            $control->getModel(),
            $row::class,
        ));
    }
}
