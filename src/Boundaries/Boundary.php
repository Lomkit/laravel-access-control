<?php

namespace Lomkit\Access\Boundaries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use ReflectionMethod;

abstract class Boundary
{
    public static function where(string $column, Closure $readerValue): ColumnBoundary
    {
        return ColumnBoundary::of($column, $readerValue);
    }

    public static function none(): NoBoundary
    {
        return new NoBoundary;
    }

    /** @internal */
    final public function applyAdmits(AccessContext $context): bool
    {
        return $this->admits($context);
    }

    /**
     * @internal
     *
     * @param  Builder<covariant Model>  $query
     */
    final public function applyScope(Builder $query, AccessContext $context): void
    {
        $query->where(function (Builder $bounded) use ($context): void {
            $this->scope($bounded, $context);
        });
    }

    /** @internal */
    final public function containsDraft(Model $draft, AccessContext $context): bool
    {
        return $this->checksInMemory() && $this->includes($draft, $context);
    }

    /** @internal */
    final public function contains(Model $row, AccessContext $context): bool
    {
        if ($this->checksInMemory()) {
            return $this->includes($row, $context);
        }

        $query = $row->newQueryWithoutScopes()->whereKey($row->getKey());

        $this->applyScope($query, $context);

        return $query->exists();
    }

    final public function checksInMemory(): bool
    {
        return (new ReflectionMethod($this, 'includes'))->getDeclaringClass()->getName() !== self::class;
    }

    /** @internal */
    final public function matches(self $other): bool
    {
        return $other::class === static::class && $this->sameDeclarationAs($other);
    }

    protected function admits(AccessContext $context): bool
    {
        return true;
    }

    /** @param  Builder<covariant Model>  $query */
    abstract protected function scope(Builder $query, AccessContext $context): void;

    protected function includes(Model $row, AccessContext $context): bool
    {
        return false;
    }

    protected function sameDeclarationAs(self $other): bool
    {
        return true;
    }
}
