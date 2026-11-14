<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class ScoutIndexesBeforeCommit implements FreshnessIssue
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
            'Scout\'s after_commit option is off, so Scout indexes a [%s] saved inside a transaction before it commits: a rolled back change can leave keys in the index that the database never kept. Set \'after_commit\' => true in config/scout.php.',
            $this->model,
        );
    }
}
