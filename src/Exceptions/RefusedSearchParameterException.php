<?php

namespace Lomkit\Access\Exceptions;

use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RefusedSearchParameterException extends UnprocessableEntityHttpException
{
    public static function named(SearchTokenEngine $engine, string $index, string $parameter): self
    {
        return new self(sprintf(
            'The %s search of the [%s] index sends [%s], which is not one of the parameters that a guarded search accepts: it accepts, as written, the parameters that only narrow, page, rank or format the documents the reader may see. Set any other parameter on the server, or leave it out.',
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

    public static function documentVector(SearchTokenEngine $engine, string $index): self
    {
        return new self(sprintf(
            'The [vector_query] of the %s search of the [%s] index searches with the vector of a stored document (id), which a guarded search refuses: the engine reads that document without the access filter. Send the vector values instead.',
            $engine->label(),
            $index,
        ));
    }
}
