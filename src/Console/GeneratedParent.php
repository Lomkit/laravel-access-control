<?php

namespace Lomkit\Access\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Access\Perimeters\ThroughParent;

final readonly class GeneratedParent
{
    public function __construct(private string $relation) {}

    /** @param  class-string<Model>  $model */
    public function isBelongsToOf(string $model): bool
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->relation) !== 1) {
            return false;
        }

        $instance = new $model;

        if (! $instance->isRelation($this->relation)) {
            return false;
        }

        $relation = $instance->{$this->relation}();

        return $relation instanceof BelongsTo && ! $relation instanceof MorphTo;
    }

    public function relation(): string
    {
        return $this->relation;
    }

    public function code(): string
    {
        return sprintf("\\%s::belongsTo('%s'),", ThroughParent::class, $this->relation);
    }
}
