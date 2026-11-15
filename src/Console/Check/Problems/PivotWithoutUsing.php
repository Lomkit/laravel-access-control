<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
final readonly class PivotWithoutUsing implements Problem
{
    public function __construct(
        private string $model,
        private string $relation,
        private string $table,
        private string $pivot,
    ) {}

    #[\Override]
    public function kind(): ProblemKind
    {
        return ProblemKind::PivotWithoutUsing;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->model;
    }

    #[\Override]
    public function message(): string
    {
        return sprintf('The [%s] relation of the [%s] model reaches the [%s] table without ->using(%s::class), so attach(), detach() and sync() through it fire no pivot event and the documents that [%s] reindexes keep their former keys. Add ->using(%s::class) to the relation.', $this->relation, $this->model, $this->table, class_basename($this->pivot), $this->pivot, class_basename($this->pivot));
    }
}
