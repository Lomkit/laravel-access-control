<?php

namespace Lomkit\Access\Perimeters\Parents;

use Lomkit\Access\Exceptions\InvalidRowContextColumnException;

/** @internal */
final readonly class RowContextColumn
{
    private const string PLAIN_COLUMN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private function __construct(private string $name) {}

    /** @throws InvalidRowContextColumnException When the column is not the plain name of a column. */
    public static function of(string $relation, string $column): self
    {
        if (preg_match(self::PLAIN_COLUMN, $column) !== 1) {
            throw InvalidRowContextColumnException::for($relation, $column);
        }

        return new self($column);
    }

    public function name(): string
    {
        return $this->name;
    }
}
