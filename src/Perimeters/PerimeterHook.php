<?php

namespace Lomkit\Access\Perimeters;

use ReflectionMethod;

/** @internal */
enum PerimeterHook: string
{
    case Allows = 'allows';
    case Includes = 'includes';
    case Scope = 'scope';

    public function callback(): string
    {
        return match ($this) {
            self::Allows => 'allowed',
            self::Includes => 'should',
            self::Scope => 'query',
        };
    }

    public function readerPosition(): int
    {
        return match ($this) {
            self::Allows, self::Includes => 0,
            self::Scope => 1,
        };
    }

    public function abilityPosition(): int
    {
        return match ($this) {
            self::Allows => 1,
            self::Includes, self::Scope => 2,
        };
    }

    public function checksRows(): bool
    {
        return $this !== self::Allows;
    }

    /** @param  class-string<Perimeter>  $perimeter */
    public function isOverriddenIn(string $perimeter): bool
    {
        $declaring = (new ReflectionMethod($perimeter, $this->value))->getDeclaringClass()->getName();

        return $declaring !== Perimeter::class && ! ($this === self::Allows && $declaring === ThroughParent::class);
    }
}
