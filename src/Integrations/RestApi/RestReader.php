<?php

namespace Lomkit\Access\Integrations\RestApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidRestReaderException;
use Lomkit\Rest\Extensions\Extension;

/** @internal */
final class RestReader
{
    /**
     * @param  class-string<Extension>  $extension
     *
     * @throws InvalidRestReaderException When the user is not an Eloquent model.
     */
    public static function of(?Authenticatable $user, string $extension): ?Model
    {
        if ($user === null || $user instanceof Model) {
            return $user;
        }

        throw InvalidRestReaderException::notAModel($user, $extension);
    }
}
