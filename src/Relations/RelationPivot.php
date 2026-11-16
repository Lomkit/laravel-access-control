<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/** @internal */
final readonly class RelationPivot
{
    /** @param  class-string<Model>  $class */
    private function __construct(
        private string $table,
        private string $class,
    ) {}

    /** @param  Relation<Model, Model, mixed>  $relation */
    public static function of(Relation $relation): ?self
    {
        return $relation instanceof BelongsToMany ? new self($relation->getTable(), $relation->getPivotClass()) : null;
    }

    public function table(): string
    {
        return $this->table;
    }

    /** @return class-string<Model> */
    public function class(): string
    {
        return $this->class;
    }
}
