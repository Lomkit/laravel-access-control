<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class ProxiedSearchTokenException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'No search token is signed for the [%s] model: the application declared Access::searchTokens(direct: false), so browsers search through a route of the application, which authenticates the reader and passes the body to Access::guardSearch(), and need no key of the search engine. Point the front end at that route, or declare Access::searchTokens(direct: true) if browsers query the engine directly. See %s',
            $model,
            SearchFromTheBrowser::DOCUMENTATION,
        ));
    }
}
