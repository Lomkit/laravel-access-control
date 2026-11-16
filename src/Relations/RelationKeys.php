<?php

namespace Lomkit\Access\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/** @internal */
final readonly class RelationKeys
{
    private function __construct(
        private string $foreign,
        private string $referenced,
    ) {}

    /** @param  Relation<Model, Model, mixed>  $relation */
    public static function of(Relation $relation): ?self
    {
        [$foreign, $referenced] = match (true) {
            $relation instanceof BelongsTo => [$relation->getForeignKeyName(), $relation->getOwnerKeyName()],
            $relation instanceof HasOneOrMany => [$relation->getForeignKeyName(), $relation->getLocalKeyName()],
            $relation instanceof BelongsToMany => [$relation->getForeignPivotKeyName(), $relation->getParentKeyName()],
            default => [null, null],
        };

        return is_string($foreign) && is_string($referenced) ? new self($foreign, $referenced) : null;
    }

    public function foreign(): string
    {
        return $this->foreign;
    }

    public function referenced(): string
    {
        return $this->referenced;
    }

    public function equals(self $other): bool
    {
        return $this->foreign === $other->foreign && $this->referenced === $other->referenced;
    }
}
