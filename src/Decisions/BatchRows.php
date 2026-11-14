<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidBatchRowException;

/** @internal */
final readonly class BatchRows
{
    /**
     * @param  list<Model>  $rows
     * @param  array<class-string<Model>, Model>  $models
     * @param  array<class-string<Model>, list<int|string>>  $keys
     */
    private function __construct(
        private array $rows,
        private array $models,
        private array $keys,
    ) {}

    /**
     * @param  iterable<mixed>  $rows
     *
     * @throws InvalidBatchRowException When a row is not an Eloquent model.
     */
    public static function of(iterable $rows): self
    {
        $models = [];
        $keys = [];
        $list = [];

        foreach ($rows as $row) {
            if (! $row instanceof Model) {
                throw InvalidBatchRowException::notAModel($row);
            }

            $list[] = $row;
            $key = $row->exists ? StoredRow::of($row)->getKey() : null;

            if (is_int($key) || is_string($key)) {
                $models[$row::class] ??= $row;
                $keys[$row::class][] = $key;
            }
        }

        return new self($list, $models, array_map(fn (array $classKeys): array => array_values(array_unique($classKeys)), $keys));
    }

    /** @return list<Model> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return list<Model> */
    public function models(): array
    {
        return array_values($this->models);
    }

    /** @return list<int|string> */
    public function keysOf(Model $model): array
    {
        return $this->keys[$model::class] ?? [];
    }
}
