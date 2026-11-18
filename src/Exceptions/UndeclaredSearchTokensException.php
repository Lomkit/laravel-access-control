<?php

namespace Lomkit\Access\Exceptions;

use LogicException;

final class UndeclaredSearchTokensException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self(sprintf(
            'No search token is signed for the [%s] model until the application declares how browsers reach the search engine. In the boot() method of a service provider, call Access::searchTokens(direct: true) when browsers query the engine with the token, which needs an engine version that keeps the filter of the token apart from the filter of the browser, or Access::searchTokens(direct: false) when browsers go through a route of the application that calls Access::guardSearch(). See %s',
            $model,
            SearchFromTheBrowser::DOCUMENTATION,
        ));
    }
}
