<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Controls\Control;

final class InvalidParentRelationException extends LogicException
{
    public static function missing(Control $control, string $model, string $relation): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control names the [%s] relation, which the [%s] model does not define. Name a belongsTo relation of the model, such as ThroughParent::belongsTo(\'post\').',
            $control::class,
            $relation,
            $model,
        ));
    }

    public static function unsupported(Control $control, string $model, string $relation, string $type): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control names the [%s] relation of the [%s] model, which is a [%s]. Only a belongsTo relation to a single parent model is supported: a child inherits the access of the one row it belongs to.',
            $control::class,
            $relation,
            $model,
            $type,
        ));
    }

    public static function inverse(Control $control, string $parent, string $inverse, string $relation, string $model): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control names [%s] as the inverse of the [%s] relation, but [%s::%s()] is not the hasMany of the [%s] model on the same keys. Name the hasMany from the parent to its children, such as inverse: \'comments\'.',
            $control::class,
            $inverse,
            $relation,
            $parent,
            $inverse,
            $model,
        ));
    }
}
