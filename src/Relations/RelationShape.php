<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/** @internal */
final readonly class RelationShape
{
    /**
     * @param  class-string<Relation<*, *, *>>  $type
     * @param  class-string<Model>  $related
     */
    private function __construct(
        private string $type,
        private string $related,
        private ?RelationKeys $keys,
        private ?RelationPivot $pivot,
    ) {}

    /** @param  Relation<Model, Model, mixed>  $relation */
    public static function of(Relation $relation): self
    {
        return new self($relation::class, $relation->getRelated()::class, RelationKeys::of($relation), RelationPivot::of($relation));
    }

    /** @param  class-string<Relation<*, *, *>>  $type */
    public function is(string $type): bool
    {
        return is_a($this->type, $type, true);
    }

    /** @return class-string<Relation<*, *, *>> */
    public function type(): string
    {
        return $this->type;
    }

    /** @return class-string<Model> */
    public function related(): string
    {
        return $this->related;
    }

    public function keys(): ?RelationKeys
    {
        return $this->keys;
    }

    public function pivot(): ?RelationPivot
    {
        return $this->pivot;
    }
}
