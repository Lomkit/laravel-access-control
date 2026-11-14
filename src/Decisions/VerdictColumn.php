<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/** @internal */
final readonly class VerdictColumn implements Expression
{
    public function __construct(
        private string $case,
        private string $alias,
    ) {}

    #[\Override]
    public function getValue(Grammar $grammar): string
    {
        return sprintf('%s as %s', $this->case, $grammar->wrap($this->alias));
    }
}
