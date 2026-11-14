<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\ModelObserver;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;

/** @internal */
final class SearchQueue
{
    private const string SYNC = 'sync';

    /** @throws MissingControlledSearchableException When the model is not searchable. */
    public static function push(ReindexWatchedRows|ReindexDocument $job, Model $searchable, Connection $changed): void
    {
        if (! method_exists($searchable, 'syncWithSearchUsing') || ! method_exists($searchable, 'syncWithSearchUsingQueue')) {
            throw MissingControlledSearchableException::forReindex($searchable::class);
        }

        if (ModelObserver::syncingDisabledFor($searchable)) {
            return;
        }

        $queued = ScoutOptions::queued();
        $connection = $queued ? $searchable->syncWithSearchUsing() : self::SYNC;
        $queue = $queued ? $searchable->syncWithSearchUsingQueue() : null;

        $job->onConnection(is_string($connection) ? $connection : null)->onQueue(is_string($queue) ? $queue : null);

        AfterCommit::once($job::class.':'.$job->uniqueId(), $changed, static function () use ($job): void {
            dispatch($job);
        });
    }
}
