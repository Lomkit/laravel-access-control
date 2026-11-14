<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidBatchRowException;
use Lomkit\Access\Exceptions\MissingControlException;

/** @internal */
final readonly class Preloader
{
    public function __construct(private DecisionMemo $memo) {}

    /**
     * @param  iterable<mixed>  $rows
     *
     * @throws InvalidBatchRowException When a row is not an Eloquent model.
     * @throws MissingControlException When no control is registered for the model of a row.
     * @throws InvalidAbilityException When the control cannot map the ability.
     */
    public function preload(Model $reader, Ability|string $ability, iterable $rows): void
    {
        if (! DecisionKey::remembers($reader)) {
            return;
        }

        $batch = BatchRows::of($rows);

        foreach ($batch->models() as $model) {
            $control = Access::controlFor($model);
            $resolved = $ability instanceof Ability ? $ability : $control->abilityFor($ability);

            (new VerdictBatch(VerdictRecorder::for($control, $reader, $resolved, $this->memo)))->run($model, $batch->keysOf($model));
        }
    }
}
