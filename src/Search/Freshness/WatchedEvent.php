<?php

namespace Lomkit\Access\Search\Freshness;

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
}
