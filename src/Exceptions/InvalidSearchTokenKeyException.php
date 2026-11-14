<?php

namespace Lomkit\Access\Exceptions;

use InvalidArgumentException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

final class InvalidSearchTokenKeyException extends InvalidArgumentException
{
    public static function blank(SearchTokenEngine $engine, string $part): self
    {
        return new self(sprintf(
            'The %s search token key has a blank %s. Pass the %s of a search-only %s key, read from your configuration, such as config(\'services.%s\').',
            $engine->label(),
            $part,
            $part,
            $engine->label(),
            strtolower($engine->label()),
        ));
    }
}
