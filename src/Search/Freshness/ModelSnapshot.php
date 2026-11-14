<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final class ModelSnapshot
{
    /** @return array<string, mixed> */
    public static function current(Model $model): array
    {
        return $model->getAttributes();
    }

    /** @return array<string, mixed> */
    public static function previous(Model $model): array
    {
        return [...$model->getAttributes(), ...$model->getPrevious()];
    }
}
