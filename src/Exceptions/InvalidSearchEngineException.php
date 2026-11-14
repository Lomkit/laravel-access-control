<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class InvalidSearchEngineException extends LogicException
{
    public static function named(string $engine): self
    {
        return new self(sprintf(
            'The [%s] class cannot be trusted as a search engine: it does not extend Laravel\Scout\Engines\Engine.',
            $engine,
        ));
    }
}
