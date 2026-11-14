<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Search\Tokens\SearchTokenEngine;

final class MissingSearchClientException extends LogicException
{
    public static function for(SearchTokenEngine $engine, string $package): self
    {
        return new self(sprintf(
            'Signing a %s search token needs the official %s client, which is not installed or did not return a token. Run composer require %s.',
            $engine->label(),
            $engine->label(),
            $package,
        ));
    }
}
