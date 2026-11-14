<?php

namespace Lomkit\Access\Search;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\TurbopufferEngine;
use Lomkit\Access\Exceptions\UnsupportedSearchEngineException;

enum SearchEngineKind
{
    case Index;
    case NativeFilters;
    case Database;

    /** @throws UnsupportedSearchEngineException When the engine cannot filter a controlled search by the access keys. */
    public static function of(mixed $engine, Model $model): self
    {
        return match (true) {
            $engine instanceof DatabaseEngine => self::Database,
            $engine instanceof CollectionEngine => throw UnsupportedSearchEngineException::collection($model),
            $engine instanceof TurbopufferEngine => self::NativeFilters,
            Container::getInstance()->make(TrustedSearchEngines::class)->trusts($engine) => self::Index,
            default => throw UnsupportedSearchEngineException::untrusted($model, $engine),
        };
    }

    public static function storesDocuments(mixed $engine): bool
    {
        return ! $engine instanceof DatabaseEngine && ! $engine instanceof CollectionEngine;
    }
}
