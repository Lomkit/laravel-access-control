<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\BatchRows;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Decisions\StoredRow;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Decisions\VerdictBatch;
use Lomkit\Access\Decisions\VerdictRecorder;
use Lomkit\Access\Exceptions\InvalidBatchRowException;

/** @phpstan-require-extends Control */
trait ReachesKeys
{
    /**
     * @param  iterable<mixed>  $rows
     * @return list<int|string>
     *
     * @throws InvalidBatchRowException When a row is not an Eloquent model, or is a row of another model.
     */
    final public function reachableKeys(Model $reader, Ability $ability, iterable $rows): array
    {
        $batch = BatchRows::of($rows);
        $recorder = VerdictRecorder::for($this, $reader, $ability, Container::getInstance()->make(DecisionMemo::class));
        $verdicts = [];

        foreach ($batch->models() as $model) {
            if (Access::controlFor($model) !== $this) {
                throw InvalidBatchRowException::foreign($this, $model);
            }

            $verdicts[$model::class] = (new VerdictBatch($recorder))->run($model, $batch->keysOf($model));
        }

        $known = $recorder->known($verdicts);
        $keys = [];

        foreach ($batch->rows() as $row) {
            $key = $row->exists ? StoredRow::of($row)->getKey() : null;

            if ((is_int($key) || is_string($key)) && $this->verdict($reader, $ability, $row, $known) === Verdict::Granted) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
