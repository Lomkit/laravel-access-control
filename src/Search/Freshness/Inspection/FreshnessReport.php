<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class FreshnessReport
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<FreshnessIssue>  $issues
     */
    public function __construct(
        private string $model,
        private array $issues,
    ) {}

    /** @return class-string<Model> */
    public function model(): string
    {
        return $this->model;
    }

    /** @return list<FreshnessIssue> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function isFresh(): bool
    {
        return $this->issues === [];
    }
}
