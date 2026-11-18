<?php

namespace Lomkit\Access\Search\Guard;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\AmbiguousSearchIndexException;
use Lomkit\Access\Exceptions\UnknownSearchIndexException;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final readonly class SearchIndexes
{
    /** @param  array<string, Model>  $models */
    private function __construct(private array $models) {}

    /** @throws AmbiguousSearchIndexException When two models are searched in the same index. */
    public static function discovered(ControlRegistry $registry): self
    {
        $models = [];

        foreach ($registry->discoveredModels() as $class) {
            $model = is_subclass_of($class, Model::class) && UsesControlledSearchable::check($class) ? new $class : null;
            $index = $model !== null && method_exists($model, 'searchableAs') ? $model->searchableAs() : null;

            if ($model === null || ! is_string($index)) {
                continue;
            }

            if (array_key_exists($index, $models)) {
                throw AmbiguousSearchIndexException::for($index, $models[$index]::class, $class);
            }

            $models[$index] = $model;
        }

        return new self($models);
    }

    /** @throws UnknownSearchIndexException When no ControlledSearchable model of a discovered control is searched in the index. */
    public function model(string $index): Model
    {
        return $this->models[$index] ?? throw UnknownSearchIndexException::named($index);
    }
}
