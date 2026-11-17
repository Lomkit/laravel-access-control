<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\Exceptions\InvalidRowContextValuesException;

/** @internal */
final readonly class RowContextValues
{
    /** @param  list<int|string>  $values */
    private function __construct(private array $values) {}

    /** @throws InvalidRowContextValuesException When the values are not a list of ints and non-empty strings. */
    public static function of(mixed $values): self
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw InvalidRowContextValuesException::notAList($values);
        }

        $unique = [];

        foreach ($values as $position => $value) {
            if (! self::isValue($value)) {
                throw InvalidRowContextValuesException::notAValue($position, $value);
            }

            $unique[(string) $value] ??= $value;
        }

        return new self(array_values($unique));
    }

    /** @return list<int|string> */
    public function all(): array
    {
        return $this->values;
    }

    public function hasSeveral(): bool
    {
        return count($this->values) > 1;
    }

    public function match(mixed $value): int|string|null
    {
        if (! self::isValue($value)) {
            return null;
        }

        foreach ($this->values as $candidate) {
            if ((string) $candidate === (string) $value) {
                return $candidate;
            }
        }

        return null;
    }

    /** @phpstan-assert-if-true int|non-empty-string $value */
    private static function isValue(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && $value !== '');
    }
}
