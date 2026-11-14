<?php

namespace Lomkit\Access\Perimeters;

use ReflectionMethod;

enum PerimeterHook: string
{
    case Allows = 'allows';
    case Includes = 'includes';
    case Scope = 'scope';
    case ScoutScope = 'scoutScope';

    public function callback(): string
    {
        return match ($this) {
            self::Allows => 'allowed',
            self::Includes => 'should',
            self::Scope => 'query',
            self::ScoutScope => 'scoutQuery',
        };
    }

    public function readerPosition(): int
    {
        return match ($this) {
            self::Allows, self::Includes => 0,
            self::Scope, self::ScoutScope => 1,
        };
    }

    public function abilityPosition(): int
    {
        return match ($this) {
            self::Allows => 1,
            self::Includes, self::Scope, self::ScoutScope => 2,
        };
    }

    public function checksRows(): bool
    {
        return $this !== self::Allows;
    }

    /** @param  class-string<Perimeter>  $perimeter */
    public function isOverriddenIn(string $perimeter): bool
    {
        return (new ReflectionMethod($perimeter, $this->value))->getDeclaringClass()->getName() !== Perimeter::class;
    }
}
