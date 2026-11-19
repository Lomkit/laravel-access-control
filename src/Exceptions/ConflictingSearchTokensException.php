<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class ConflictingSearchTokensException extends LogicException
{
    public static function declared(bool $first, bool $second): self
    {
        return new self(sprintf(
            'The application declared Access::searchTokens(direct: %s), then Access::searchTokens(direct: %s). An application makes one choice: browsers query the search engine directly with a signed token, or go through a route of the application that calls Access::guardSearch(). Keep one declaration. See %s',
            var_export($first, true),
            var_export($second, true),
            SearchFromTheBrowser::DOCUMENTATION,
        ));
    }
}
