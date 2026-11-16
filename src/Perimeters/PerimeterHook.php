<?php

namespace Lomkit\Access\Perimeters;

use ReflectionMethod;

enum PerimeterHook: string
{
    case Allows = 'allows';
    case Includes = 'includes';
    case Scope = 'scope';

    private const array PACKAGE_CLASSES = [Perimeter::class, ThroughParent::class];

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

        return ! in_array($declaring, self::PACKAGE_CLASSES, true);
    }
}
