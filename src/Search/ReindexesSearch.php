<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Access\Search\Freshness\PivotDocuments;

/** @phpstan-require-extends Pivot */
trait ReindexesSearch
{
    /** @internal */
    public static function bootReindexesSearch(): void
    {
        $ensureResolvable = static function (Pivot $pivot): void {
            PivotDocuments::ensureResolvable($pivot);
        };
        $reindex = static function (Pivot $pivot): void {
            PivotDocuments::reindex($pivot);
        };

        static::creating($ensureResolvable);
        static::updating($ensureResolvable);
        static::deleting($ensureResolvable);
        static::created($reindex);
        static::updated($reindex);
        static::deleted($reindex);
    }
}
