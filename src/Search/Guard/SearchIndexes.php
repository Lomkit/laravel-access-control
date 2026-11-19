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
    /** @param  array<string, list<Model>>  $models */
    private function __construct(private array $models) {}

    public static function discovered(ControlRegistry $registry): self
    {
        $models = [];

        foreach ($registry->discoveredModels() as $class) {
            $model = is_subclass_of($class, Model::class) && UsesControlledSearchable::check($class) ? new $class : null;
            $index = $model !== null && method_exists($model, 'searchableAs') ? $model->searchableAs() : null;

            if ($model !== null && is_string($index)) {
                $models[$index][] = $model;
            }
        }

        return new self($models);
    }

    /**
     * @throws UnknownSearchIndexException When no ControlledSearchable model of a discovered control is searched in the index.
     * @throws AmbiguousSearchIndexException When two models are searched in the index.
     */
    public function model(string $index): Model
    {
        $models = $this->models[$index] ?? throw UnknownSearchIndexException::named($index);

        if (count($models) > 1) {
            throw AmbiguousSearchIndexException::for($index, $models[0]::class, $models[1]::class);
        }

        return $models[0];
    }
}
