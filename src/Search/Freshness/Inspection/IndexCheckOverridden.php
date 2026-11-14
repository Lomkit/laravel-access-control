<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class IndexCheckOverridden implements FreshnessIssue
{
    /** @param  class-string<Model>  $model */
    public function __construct(private string $model) {}

    #[\Override]
    public function model(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf(
            'The [%s] model overrides searchIndexShouldBeUpdated(). When it returns false, the package compares the access keys before and after the save and reindexes the document only when they differ, which computes the keys twice for that save.',
            $this->model,
        );
    }
}
