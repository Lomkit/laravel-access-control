<?php

namespace Lomkit\Access\Exceptions;

use LogicException;
use Lomkit\Access\Ability;

final class UnsearchableThroughParentException extends LogicException
{
    public static function missingInverse(string $control, string $relation, string $parent): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control through the [%s] relation has no inverse, but the documents of its rows carry the access keys of the [%s] model. When those keys change, the package reindexes the children through the hasMany from the parent to them, so they never keep keys their parent lost: name it, such as ThroughParent::belongsTo(\'%s\', inverse: \'comments\').',
            $control,
            $relation,
            $parent,
            $relation,
        ));
    }

    public static function mappedView(string $control, string $relation, Ability $ability): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control through the [%s] relation delegates view to the [%s] ability of the parent, but a search only knows the keys that open the parent for view. Delegate view to view on a model that is searched, through Scout or through an index of your own.',
            $control,
            $relation,
            $ability,
        ));
    }

    public static function unsearchableBoundary(string $control, string $relation, string $parent): self
    {
        return new self(sprintf(
            'The ThroughParent perimeter of the [%s] control through the [%s] relation inherits the access of the [%s] control, whose boundary has no search form, so its rows could not be searched through their parent. Give that boundary search keys, or use Boundary::where().',
            $control,
            $relation,
            $parent,
        ));
    }
}
