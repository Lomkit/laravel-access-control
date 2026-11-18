<?php

namespace Lomkit\Access\Exceptions;

use Lomkit\Access\Search\Tokens\SearchTokenEngine;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RefusedSearchFilterException extends UnprocessableEntityHttpException
{
    public static function notSelfContained(SearchTokenEngine $engine, string $index, string $parameter, string $filter): self
    {
        return new self(sprintf(
            'The [%s] [%s] of the %s search of the [%s] index is not a self-contained filter. A guarded search ANDs it with the access filter of the reader, so it must be a complete expression on its own: close every parenthesis and every quoted value.',
            $parameter,
            $filter,
            $engine->label(),
            $index,
        ));
    }

    public static function escaped(SearchTokenEngine $engine, string $index, string $parameter): self
    {
        return new self(sprintf(
            'The [%s] of the %s search of the [%s] index holds a backslash, which a guarded search refuses: the package cannot tell where an escaped value ends. Leave backslashes out of the filter.',
            $parameter,
            $engine->label(),
            $index,
        ));
    }

    public static function invalidType(SearchTokenEngine $engine, string $index, string $parameter, string $expected): self
    {
        return new self(sprintf(
            'The [%s] of the %s search of the [%s] index must be %s.',
            $parameter,
            $engine->label(),
            $index,
            $expected,
        ));
    }
}
