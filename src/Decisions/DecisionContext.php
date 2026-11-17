<?php

namespace Lomkit\Access\Decisions;

use Lomkit\Access\Exceptions\InvalidDecisionContextException;

/** @internal */
final readonly class DecisionContext
{
    /** @param  array<string, string|int|bool|null>  $values */
    private function __construct(private array $values) {}

    public static function none(): self
    {
        return new self([]);
    }

    /** @throws InvalidDecisionContextException When the resolver returns something else than an array of scalar values keyed by name. */
    public static function of(mixed $values): self
    {
        if (! is_array($values)) {
            throw InvalidDecisionContextException::notAnArray($values);
        }

        $context = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw InvalidDecisionContextException::unnamed($key);
            }

            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_bool($value)) {
                throw InvalidDecisionContextException::notScalar($key, $value);
            }

            $context[$key] = $value;
        }

        ksort($context, SORT_STRING);

        return new self($context);
    }

    /** @return array<string, string|int|bool|null> */
    public function values(): array
    {
        return $this->values;
    }

    public function equals(self $other): bool
    {
        return $this->values === $other->values;
    }
}
