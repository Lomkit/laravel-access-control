<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;

/** @internal */
final class ModelEngine
{
    /**
     * @param  class-string<Model>  $model
     *
     * @throws MissingControlledSearchableException When the model is not searchable.
     */
    public static function of(string $model): mixed
    {
        $instance = new $model;

        if (! method_exists($instance, 'searchableUsing')) {
            throw MissingControlledSearchableException::forReindex($model);
        }

        return $instance->searchableUsing();
    }
}
