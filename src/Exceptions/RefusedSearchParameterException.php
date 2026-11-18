<?php

namespace Lomkit\Access\Exceptions;

use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RefusedSearchParameterException extends UnprocessableEntityHttpException
{
    public static function named(SearchTokenEngine $engine, string $index, string $parameter): self
    {
        return new self(sprintf(
            'The %s search of the [%s] index sends [%s], which a guarded search refuses: a browser cannot choose the key, the stored settings or the documents that the engine adds to a search, since they could bypass the access filter of the reader. Set it on the server, or leave it out.',
            $engine->label(),
            $index,
            $parameter,
        ));
    }

    public static function joined(SearchTokenEngine $engine, string $index, string $parameter): self
    {
        return new self(sprintf(
            'The [%s] of the %s search of the [%s] index references another collection, which a guarded search refuses: the access filter of the reader applies to the searched collection only.',
            $parameter,
            $engine->label(),
            $index,
        ));
    }
}
