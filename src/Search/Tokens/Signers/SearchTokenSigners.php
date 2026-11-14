<?php

namespace Lomkit\Access\Search\Tokens\Signers;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Engines\AlgoliaEngine;
use Laravel\Scout\Engines\MeilisearchEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Exceptions\UnsupportedSearchTokenEngineException;

/** @internal */
final class SearchTokenSigners
{
    /** @throws UnsupportedSearchTokenEngineException When the engine has no browser search token. */
    public static function for(mixed $engine, Model $model): SearchTokenSigner
    {
        return match (true) {
            $engine instanceof MeilisearchEngine => new MeilisearchSigner($engine),
            $engine instanceof TypesenseEngine => new TypesenseSigner($engine),
            $engine instanceof AlgoliaEngine => new AlgoliaSigner,
            default => throw UnsupportedSearchTokenEngineException::for($model, $engine),
        };
    }
}
