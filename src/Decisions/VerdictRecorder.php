<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;

/** @internal */
final readonly class VerdictRecorder
{
    private function __construct(
        private DecisionMemo $memo,
        private AccessContext $context,
        private ?SqlCheck $check,
    ) {}

    public static function for(Control $control, Model $reader, Ability $ability, DecisionMemo $memo): self
    {
        $context = AccessContext::of($reader, $ability);
        $perimeters = $control->declaration()->reachedBy($context);
        $check = $perimeters->isEmpty() ? null : SqlCheck::for($control, $perimeters, $context);

        return new self($memo, $context, $check?->needsSql() === true ? $check : null);
    }

    public function verdictCase(Model $model): ?VerdictCase
    {
        return $this->check?->verdictCase($model);
    }

    /** @param  class-string<Model>  $model */
    public function record(string $model, mixed $row, Verdict $verdict): void
    {
        if ($this->check === null || ! in_array($verdict, [Verdict::Granted, Verdict::OutsideRows, Verdict::OutsideBoundary], true)) {
            return;
        }

        $key = DecisionKey::of($this->context, $model, $row, $this->check->fingerprint());

        if ($key !== null) {
            $this->memo->remember($key, $verdict);
        }
    }
}
