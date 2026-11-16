<?php

namespace Lomkit\Access\Console\Check\Checks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Problems\PivotWithoutUsing;
use Lomkit\Access\Console\Check\Problems\UnreadableRelation;
use Lomkit\Access\Relations\RelationInspector;
use Lomkit\Access\Relations\RelationPivot;
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
            foreach ($this->pivotsOf($findings, $model) as $name => $pivot) {
                $relations[] = [$model, $name, $pivot->table(), $pivot->class()];

                if ($this->reindexes($pivot->class())) {
                    $reindexing[$pivot->table()] = $pivot->class();
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
     * @return array<string, RelationPivot>
     */
    private function pivotsOf(Findings $findings, string $model): array
    {
        $pivots = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $this->declaresBelongsToMany($method)) {
                continue;
            }

            try {
                $pivot = RelationInspector::shape(new $model, $method->getName())?->pivot();
            } catch (Throwable $exception) {
                $findings->problem(new UnreadableRelation($model, $method->getName(), $exception->getMessage()));

                continue;
            }

            if ($pivot !== null) {
                $pivots[$method->getName()] = $pivot;
            }
        }

        return $pivots;
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
