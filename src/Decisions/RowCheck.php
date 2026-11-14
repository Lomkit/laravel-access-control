<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\ReachedPerimeters;

/** @internal */
final readonly class RowCheck
{
    public function __construct(
        private Control $control,
        private DecisionMemo $memo,
    ) {}

    public function verdict(Model $row, ReachedPerimeters $perimeters, AccessContext $context): Verdict
    {
        $boundary = $this->control->declaration()->boundary();

        if ($boundary->checksInMemory() && ! $boundary->contains($row, $context)) {
            return Verdict::OutsideBoundary;
        }

        $check = SqlCheck::for($this->control, $perimeters, $context);

        if ($perimeters->includesInMemory($row, $context)) {
            return $this->insideBoundary($row, $check, $context);
        }

        if (! $check->needsSql()) {
            return Verdict::OutsideRows;
        }

        return $this->recall($row, $check, $context) ?? $check->verdictCase($row)?->verdictOf($row) ?? Verdict::OutsideRows;
    }

    private function insideBoundary(Model $row, SqlCheck $check, AccessContext $context): Verdict
    {
        if (! $check->checksBoundary()) {
            return Verdict::Granted;
        }

        $memoized = $this->recall($row, $check, $context);

        if ($memoized !== null) {
            return $memoized === Verdict::OutsideRows ? Verdict::Granted : $memoized;
        }

        return $check->boundaryCase($row)->verdictOf($row);
    }

    private function recall(Model $row, SqlCheck $check, AccessContext $context): ?Verdict
    {
        $key = DecisionKey::of($context, $row::class, $row->getKey(), $check->fingerprint());

        return $key === null ? null : $this->memo->recall($key);
    }
}
