<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final class StoredRow
{
    public static function of(Model $row): Model
    {
        if (! $row->isDirty()) {
            return $row;
        }

        $stored = $row->newInstance([], true);
        $stored->setRawAttributes($row->getRawOriginal(), true);
        $stored->wasRecentlyCreated = $row->wasRecentlyCreated;

        return $stored;
    }
}
