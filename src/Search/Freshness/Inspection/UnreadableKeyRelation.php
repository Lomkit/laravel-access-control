<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Search\SearchKeys;

/** @internal */
final readonly class UnreadableKeyRelation implements KeyRelationIssue
{
    /**
     * @param  class-string<Model>  $model
     * @param  class-string<Model>  $owner
     */
    public function __construct(
        private string $model,
        private SearchKeys $keys,
        private string $owner,
        private string $relation,
        private string $reason,
    ) {}

    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    #[\Override]
    public function keys(): string
    {
        return $this->keys->name();
    }

    #[\Override]
    public function declaredKeys(): SearchKeys
    {
        return $this->keys;
    }

    /** @return class-string<Model> */
    public function owner(): string
    {
        return $this->owner;
    }

    public function relation(): string
    {
        return $this->relation;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] search keys of the [%s] model read the [%s] relation of the [%s] model, which throws when it is read without a database connection, so its freshness cannot be checked: %s',
            $this->keys->name(),
            $this->model,
            $this->relation,
            $this->owner,
            $this->reason,
        );
    }
}
