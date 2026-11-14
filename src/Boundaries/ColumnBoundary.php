<?php

namespace Lomkit\Access\Boundaries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\InvalidBoundaryValueException;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\ParameterType;
use Lomkit\Access\Search\SearchKeys;
use ReflectionFunction;

final class ColumnBoundary extends Boundary
{
    /** @param  list<string>  $readOnlyColumns */
    private function __construct(
        private readonly string $column,
        private readonly Closure $readerValue,
        private readonly ParameterType $reader,
        private readonly array $readOnlyColumns,
    ) {}

    /** @internal */
    public static function of(string $column, Closure $readerValue): self
    {
        return new self($column, $readerValue, ParameterType::at(new ReflectionFunction($readerValue), 0), []);
    }

    public function readOnlyWhenNotNull(string $column): self
    {
        return new self($this->column, $this->readerValue, $this->reader, [...$this->readOnlyColumns, $column]);
    }

    #[\Override]
    protected function admits(AccessContext $context): bool
    {
        if (! $this->reader->admits($context->reader())) {
            return false;
        }

        return ($this->readerValue)($context->reader()) !== null;
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $value = $this->valueFor($context, $query->getModel());

        if ($value === null) {
            $query->whereRaw('0=1');

            return;
        }

        $query->where($query->qualifyColumn($this->column), $value);

        foreach ($this->readOnlyColumnsFor($context) as $column) {
            $query->whereNull($query->qualifyColumn($column));
        }
    }

    #[\Override]
    protected function includes(Model $row, AccessContext $context): bool
    {
        $value = $this->valueFor($context, $row);

        if ($value === null) {
            return false;
        }

        if (! $this->sameValue($row->getAttribute($this->column), $value)) {
            return false;
        }

        foreach ($this->readOnlyColumnsFor($context) as $column) {
            if ($row->getAttribute($column) !== null) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    protected function searchKeys(): SearchKeys
    {
        return SearchKeys::ofColumn($this->column, $this->readerValue);
    }

    #[\Override]
    protected function sameDeclarationAs(Boundary $other): bool
    {
        return $other instanceof self
            && $this->column === $other->column
            && $this->readOnlyColumns === $other->readOnlyColumns
            && ClosureShape::of($this->readerValue)->matches(ClosureShape::of($other->readerValue));
    }

    /** @throws InvalidBoundaryValueException When the reader value is neither an int, a string nor null. */
    private function valueFor(AccessContext $context, Model $model): int|string|null
    {
        if (! $this->reader->admits($context->reader())) {
            return null;
        }

        $value = ($this->readerValue)($context->reader());

        if ($value === null || is_int($value) || is_string($value)) {
            return $value;
        }

        throw InvalidBoundaryValueException::for($model, $this->column, $value);
    }

    /** @return list<string> */
    private function readOnlyColumnsFor(AccessContext $context): array
    {
        return $context->ability()->is(Ability::view()) ? [] : $this->readOnlyColumns;
    }

    private function sameValue(mixed $rowValue, int|string $readerValue): bool
    {
        if (! is_int($rowValue) && ! is_string($rowValue)) {
            return false;
        }

        return (string) $rowValue === (string) $readerValue;
    }
}
