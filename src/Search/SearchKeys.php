<?php

namespace Lomkit\Access\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\DuplicateReindexException;
use Lomkit\Access\Exceptions\InvalidSearchKeyNameException;
use Lomkit\Access\Exceptions\InvalidSearchKeyReaderException;
use Lomkit\Access\Exceptions\UndeclaredSearchKeyRelationException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Perimeters\ClosureShape;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Search\Freshness\ReindexRules;
use Lomkit\Access\Search\Freshness\ReindexWhen;

final readonly class SearchKeys
{
    private const string NAME = '/^[a-z0-9_]+$/';

    /** @param  list<string>  $load */
    private function __construct(
        private string $name,
        private Closure $row,
        private SearchKeyReader $reader,
        private array $load,
        private ReindexRules $reindexes,
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

        return new self($name, $row, SearchKeyReader::of($reader), $load, ReindexRules::none());
    }

    /**
     * @internal
     *
     * @param  list<string>  $load
     *
     * @throws InvalidSearchKeyNameException When the name is not made of lowercase letters, digits and underscores.
     * @throws UnrestrictedPerimeterCallbackException When the perimeter is unrestricted.
     * @throws DuplicatePerimeterCallbackException When the perimeter already declares search keys.
     * @throws InvalidSearchKeyReaderException When the reader closure takes a parameter that the package cannot fill.
     */
    public static function declaredBy(Perimeter $perimeter, string $name, Closure $row, Closure $reader, array $load): self
    {
        if ($perimeter->declaredSearchKeys() !== null) {
            throw DuplicatePerimeterCallbackException::searchKeys($perimeter);
        }

        if ($perimeter->isUnrestricted()) {
            throw UnrestrictedPerimeterCallbackException::for($perimeter, 'searchKeys');
        }

        if (! self::isValidName($name)) {
            throw InvalidSearchKeyNameException::forPerimeter($perimeter, $name);
        }

        return self::of($name, $row, $reader, $load)->checkedFor($perimeter);
    }

    /**
     * @internal
     *
     * @throws InvalidSearchKeyReaderException When the reader closure takes a parameter that the package cannot fill.
     */
    public function checkedFor(Perimeter|Boundary $declarer): self
    {
        $parameter = $this->reader->unfillableParameter();

        if ($parameter !== null) {
            throw InvalidSearchKeyReaderException::for($declarer, $this->name, $parameter);
        }

        return $this;
    }

    /** @internal */
    public static function ofColumn(string $column, Closure $reader): self
    {
        return new self(
            SearchKeyValues::component($column),
            static fn (Model $row): mixed => $row->getAttribute($column),
            SearchKeyReader::withoutContext($reader),
            [],
            ReindexRules::none(),
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

    /**
     * @internal
     *
     * @throws DuplicateReindexException When the keys already reindex for the same model.
     */
    public function reindexingWhen(ReindexWhen $reindex, Perimeter $perimeter): self
    {
        return new self($this->name, $this->row, $this->reader, $this->load, $this->reindexes->with($reindex, $perimeter));
    }

    /** @internal */
    public function reindexes(): ReindexRules
    {
        return $this->reindexes;
    }

    /** @internal */
    public function admits(Model $reader): bool
    {
        return $this->reader->admits($reader);
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

        return SearchKeyValues::keys($this->name, $this->reader->values($context), $reader);
    }

    /** @internal */
    public function matches(self $other): bool
    {
        return $this->name === $other->name
            && $this->load === $other->load
            && ClosureShape::of($this->row)->matches(ClosureShape::of($other->row))
            && $this->reader->matches($other->reader)
            && $this->reindexes->matches($other->reindexes);
    }
}
