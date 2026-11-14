<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;

/** @internal */
enum WatchedEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    public function eventName(): string
    {
        return "eloquent.{$this->value}: *";
    }

    public function triggers(ReindexWhen $reindex, Model $instance): bool
    {
        return $this !== self::Updated || $instance->wasChanged($reindex->changed());
    }
}
