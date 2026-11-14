<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class AbilityColumn
{
    public function __construct(
        private string $alias,
        private VerdictCase $case,
        private VerdictRecorder $recorder,
    ) {}

    /** @param  array<string, mixed>  $attributes */
    public function record(Model $row, mixed $key, array $attributes): void
    {
        if (array_key_exists($this->alias, $attributes)) {
            $this->recorder->record($row::class, $key, $this->case->verdictFrom($attributes[$this->alias]));
        }
    }
}
