<?php

namespace Lomkit\Access\Relations;

use Closure;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Lomkit\Access\Exceptions\RelationInspectionQueryException;
use Throwable;

/** @internal */
final class RelationInspector
{
    /** @throws RelationInspectionQueryException When the relation method runs a query. */
    public static function shape(Model $model, string $relation): ?RelationShape
    {
        return self::inspect($model, $relation, fn (mixed $instance): ?RelationShape => $instance instanceof Relation ? RelationShape::of($instance) : null);
    }

    /** @throws RelationInspectionQueryException When the relation method runs a query. */
    public static function returnedType(Model $model, string $relation): string
    {
        return self::inspect($model, $relation, get_debug_type(...));
    }

    /**
     * @template TResult
     *
     * @param  Closure(mixed): TResult  $read
     * @return TResult
     */
    private static function inspect(Model $model, string $relation, Closure $read): mixed
    {
        $resolver = Model::getConnectionResolver();
        Model::setConnectionResolver(new InertConnectionResolver($model, $relation, self::defaultConnection($resolver)));

        try {
            return $read($model->{$relation}());
        } catch (Throwable $exception) {
            $query = self::queryIn($exception);

            if ($query === null) {
                throw $exception;
            }

            throw $exception instanceof QueryException ? RelationInspectionQueryException::for($model::class, $relation, $exception->getConnectionName(), $exception) : $query;
        } finally {
            self::restore($resolver);
        }
    }

    private static function defaultConnection(?ConnectionResolverInterface $resolver): string
    {
        $default = $resolver?->getDefaultConnection();

        return is_string($default) ? $default : '';
    }

    private static function queryIn(Throwable $exception): ?RelationInspectionQueryException
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof RelationInspectionQueryException) {
                return $cause;
            }
        }

        return null;
    }

    private static function restore(?ConnectionResolverInterface $resolver): void
    {
        if ($resolver === null) {
            Model::unsetConnectionResolver();

            return;
        }

        Model::setConnectionResolver($resolver);
    }
}
