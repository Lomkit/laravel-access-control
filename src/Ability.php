<?php

namespace Lomkit\Access;

use Lomkit\Access\Exceptions\InvalidAbilityException;
use Stringable;

final readonly class Ability implements Stringable
{
    private function __construct(private string $name) {}

    public static function view(): self
    {
        return new self('view');
    }

    public static function create(): self
    {
        return new self('create');
    }

    public static function update(): self
    {
        return new self('update');
    }

    public static function delete(): self
    {
        return new self('delete');
    }

    public static function restore(): self
    {
        return new self('restore');
    }

    public static function forceDelete(): self
    {
        return new self('forceDelete');
    }

    /**
     * @throws InvalidAbilityException When the name is empty, is not valid UTF-8 or contains whitespace.
     */
    public static function of(string $name): self
    {
        if ($name === '') {
            throw InvalidAbilityException::empty();
        }

        $whitespace = preg_match('/\s/u', $name);

        if ($whitespace === false) {
            throw InvalidAbilityException::malformed($name);
        }

        if ($whitespace === 1) {
            throw InvalidAbilityException::withWhitespace($name);
        }

        return new self($name);
    }

    public function is(self|string $other): bool
    {
        if ($other instanceof self) {
            return $other->name === $this->name;
        }

        return $other === $this->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
