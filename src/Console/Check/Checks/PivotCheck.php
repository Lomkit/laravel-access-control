<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Problems\PivotWithoutUsing;
use Lomkit\Access\Search\ReindexesSearch;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/** @internal */
final class PivotCheck
{
    /**
     * @param  list<class-string<Model>>  $models
     * @return int<0, max>
     */
    public function run(Findings $findings, array $models): int
    {
        $relations = [];
        $reindexing = [];

        foreach ($models as $model) {
            foreach ($this->relationsOf($model) as $name => $relation) {
                $relations[] = [$model, $name, $relation->getTable(), $relation->getPivotClass()];

                if ($this->reindexes($relation->getPivotClass())) {
                    $reindexing[$relation->getTable()] = $relation->getPivotClass();
                }
            }
        }

        foreach ($relations as [$model, $name, $table, $pivot]) {
            if (isset($reindexing[$table]) && ! $this->reindexes($pivot)) {
                $findings->problem(new PivotWithoutUsing($model, $name, $table, $reindexing[$table]));
            }
        }

        return count($relations);
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, BelongsToMany<Model, Model>>
     */
    private function relationsOf(string $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $this->declaresBelongsToMany($method)) {
                continue;
            }

            try {
                $relation = $method->invoke(new $model);
            } catch (Throwable) {
                continue;
            }

            if ($relation instanceof BelongsToMany) {
                $relations[$method->getName()] = $relation;
            }
        }

        return $relations;
    }

    private function declaresBelongsToMany(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return ! $method->isStatic()
            && $method->getNumberOfRequiredParameters() === 0
            && $type instanceof ReflectionNamedType
            && is_a($type->getName(), BelongsToMany::class, true);
    }

    private function reindexes(string $pivot): bool
    {
        return in_array(ReindexesSearch::class, class_uses_recursive($pivot), true);
    }
}
