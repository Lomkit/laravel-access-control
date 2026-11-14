<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Controls\PerimeterConstraints;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ReachedPerimeters;

/** @internal */
final readonly class SqlCheck
{
    /** @param  array<string, Perimeter>  $perimeters */
    private function __construct(
        private Control $control,
        private AccessContext $context,
        private bool $boundary,
        private array $perimeters,
    ) {}

    public static function for(Control $control, ReachedPerimeters $reached, AccessContext $context): self
    {
        $boundary = ! $control->declaration()->boundary()->checksInMemory();

        return new self($control, $context, $boundary, $reached->checkedInSql());
    }

    public function needsSql(): bool
    {
        return $this->boundary || $this->perimeters !== [];
    }

    public function checksBoundary(): bool
    {
        return $this->boundary;
    }

    public function fingerprint(): string
    {
        return ($this->boundary ? 'boundary' : 'rows').':'.implode(',', array_keys($this->perimeters));
    }

    public function verdictCase(Model $model): ?VerdictCase
    {
        $rows = $this->perimeters === [] ? Verdict::OutsideRows : VerdictCase::rows($this->rowsOf($model));

        if ($this->boundary) {
            return VerdictCase::bounded($this->boundaryOf($model), $rows);
        }

        return $rows instanceof VerdictCase ? $rows : null;
    }

    public function boundaryCase(Model $model): VerdictCase
    {
        return VerdictCase::bounded($this->boundaryOf($model), Verdict::Granted);
    }

    private function boundaryOf(Model $model): WhereClause
    {
        $query = $model->newQueryWithoutScopes();

        $this->control->declaration()->boundary()->applyScope($query, $this->context);

        return WhereClause::of($query);
    }

    private function rowsOf(Model $model): WhereClause
    {
        $query = $model->newQueryWithoutScopes();

        (new PerimeterConstraints($this->control))->apply($query, $this->perimeters, $this->context);

        return WhereClause::of($query);
    }
}
