<?php

namespace Lomkit\Access\Search\Parents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;

/** @internal */
final class InverseRelation
{
    /**
     * @return HasMany<Model, Model>
     *
     * @throws InvalidReindexRowsException When the inverse is no longer a hasMany of the parent.
     */
    public static function of(Model $parent, string $inverse): HasMany
    {
        $relation = $parent->isRelation($inverse) ? $parent->{$inverse}() : null;

        if (! $relation instanceof HasMany) {
            throw InvalidReindexRowsException::returned($inverse, $parent::class, get_debug_type($relation));
        }

        return $relation;
    }
}
