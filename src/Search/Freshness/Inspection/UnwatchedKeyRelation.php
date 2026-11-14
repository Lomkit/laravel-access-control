<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class UnwatchedKeyRelation implements FreshnessIssue
{
    /**
     * @param  class-string<Model>  $model
     * @param  class-string<Model>|null  $related
     */
    public function __construct(
        private string $model,
        private string $keys,
        private string $relation,
        private ?string $related,
    ) {}

    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    public function keys(): string
    {
        return $this->keys;
    }

    public function relation(): string
    {
        return $this->relation;
    }

    /** @return class-string<Model>|null */
    public function related(): ?string
    {
        return $this->related;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] search keys of the [%s] model read the [%s] relation, but nothing reindexes the documents when [%s] changes, so they keep their former keys until they are reindexed. Declare reindexWhen(%s::class, ...) on the perimeter, use ReindexesSearch on the pivot, or call searchable() after the change.',
            $this->keys,
            $this->model,
            $this->relation,
            $this->related ?? $this->relation,
            $this->related === null ? 'Related' : class_basename($this->related),
        );
    }
}
