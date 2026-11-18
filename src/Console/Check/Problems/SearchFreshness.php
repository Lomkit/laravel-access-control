<?php

namespace Lomkit\Access\Console\Check\Problems;

use Lomkit\Access\Search\Freshness\Inspection\FreshnessIssue;

/** @internal */
final readonly class SearchFreshness implements PositionedProblem
{
    public function __construct(
        private FreshnessIssue $issue,
        private ?string $position,
    ) {}

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
    public function position(): ?string
    {
        return $this->position;
    }

    #[\Override]
    public function message(): string
    {
        return $this->issue->message();
    }
}
