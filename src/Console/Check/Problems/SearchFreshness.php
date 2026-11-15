<?php

namespace Lomkit\Access\Console\Check\Problems;

use Lomkit\Access\Search\Freshness\Inspection\FreshnessIssue;

/** @internal */
final readonly class SearchFreshness implements Problem
{
    public function __construct(private FreshnessIssue $issue) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::SearchFreshness;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->issue->model();
    }

    #[\Override]
    public function message(): string
    {
        return $this->issue->message();
    }
}
