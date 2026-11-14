<?php

namespace Lomkit\Access\Search;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final class UsesControlledSearchable
{
    /** @param  Model|class-string  $model */
    public static function check(Model|string $model): bool
    {
        return in_array(ControlledSearchable::class, class_uses_recursive($model), true);
    }
}
