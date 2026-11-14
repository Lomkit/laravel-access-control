<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

final class MismatchedSearchTokenKeyException extends LogicException
{
    public static function for(SearchTokenEngine $key, SearchTokenEngine $engine, string $model): self
    {
        return new self(sprintf(
            'A %s key cannot sign a search token for the [%s] model, which searches with the %s engine. Pass %s.',
            $key->label(),
            $model,
            $engine->label(),
            $engine->keyFactory(),
        ));
    }
}
