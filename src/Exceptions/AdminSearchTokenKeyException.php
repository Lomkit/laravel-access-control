<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

final class AdminSearchTokenKeyException extends LogicException
{
    public static function for(SearchTokenEngine $engine, string $model): self
    {
        return new self(sprintf(
            'The key passed to sign a %1$s search token for the [%2$s] model is the admin key of config [%3$s]. A token signed with it would carry the rights of that key to the browser. Create a search-only %1$s key, limited to the index of [%2$s], and pass it instead.',
            $engine->label(),
            $model,
            $engine->adminKeyOption(),
        ));
    }
}
