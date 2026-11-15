<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class InvalidRestReaderException extends LogicException
{
    public static function notAModel(object $user, string $extension): self
    {
        return new self(sprintf(
            'The [%s] rest-api extension received a user of the [%s] class, which is not an Eloquent model, so no control can decide its rows. Authenticate users that extend Illuminate\Database\Eloquent\Model, or add [%s] to rest.disabled_extensions.',
            $extension,
            $user::class,
            $extension,
        ));
    }
}
