<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\InvalidSearchKeyNameException;
use Lomkit\Access\Exceptions\UndeclaredSearchKeyRelationException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\ParameterType;
use Lomkit\Access\Perimeters\Perimeter;
use ReflectionFunction;

final readonly class SearchKeys
{
    private const string NAME = '/^[a-z0-9_]+$/';

    /** @param  list<string>  $load */
    private function __construct(
        private string $name,
        private Closure $row,
        private Closure $reader,
        private ParameterType $readerType,
        private array $load,
    ) {}

    /**
     * @param  list<string>  $load
     *
     * @throws InvalidSearchKeyNameException When the name is not made of lowercase letters, digits and underscores.
     */
    public static function of(string $name, Closure $row, Closure $reader, array $load = []): self
    {
        if (! self::isValidName($name)) {
            throw InvalidSearchKeyNameException::named($name);
        }

        return new self($name, $row, $reader, ParameterType::at(new ReflectionFunction($reader), 0), $load);
    }

    /**
     * @internal
     *
     * @param  list<string>  $load
     *
     * @throws InvalidSearchKeyNameException When the name is not made of lowercase letters, digits and underscores.
     * @throws UnrestrictedPerimeterCallbackException When the perimeter is unrestricted.
     */
    public static function declaredBy(Perimeter $perimeter, string $name, Closure $row, Closure $reader, array $load): self
    {
        if ($perimeter->isUnrestricted()) {
            throw UnrestrictedPerimeterCallbackException::for($perimeter, 'searchKeys');
        }

        if (! self::isValidName($name)) {
            throw InvalidSearchKeyNameException::forPerimeter($perimeter, $name);
        }

        return self::of($name, $row, $reader, $load);
    }

    /** @internal */
    public static function ofColumn(string $column, Closure $reader): self
    {
        return new self(
            SearchKeyValues::component($column),
            static fn (Model $row): mixed => $row->getAttribute($column),
            $reader,
            ParameterType::at(new ReflectionFunction($reader), 0),
            [],
        );
    }

    /** @internal */
    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME, $name) === 1;
    }

    /** @internal */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function load(): array
    {
        return $this->load;
    }

    /** @internal */
    public function admits(Model $reader): bool
    {
        return $this->readerType->admits($reader);
    }

    /**
     * @internal
     *
     * @return list<string>
     *
     * @throws UndeclaredSearchKeyRelationException When the row callback reads a relation that load does not declare.
     */
    public function rowKeys(Model $row): array
    {
        try {
            $values = ($this->row)($row);
        } catch (LazyLoadingViolationException $exception) {
            throw UndeclaredSearchKeyRelationException::for($this->name, $exception);
        }

        return SearchKeyValues::keys($this->name, $values, $row);
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function readerKeys(AccessContext $context): array
    {
        $reader = $context->reader();

        if (! $this->admits($reader)) {
            return [];
        }

        return SearchKeyValues::keys($this->name, ($this->reader)($reader), $reader);
    }

    /** @internal */
    public function fingerprint(): string
    {
        return $this->name."\n".ClosureSource::of($this->row);
    }

    /** @internal */
    public function matches(self $other): bool
    {
        return $this->name === $other->name
            && $this->load === $other->load
            && ClosureShape::of($this->row)->matches(ClosureShape::of($other->row))
            && ClosureShape::of($this->reader)->matches(ClosureShape::of($other->reader));
    }
}
