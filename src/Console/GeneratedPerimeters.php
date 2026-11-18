<?php

namespace Lomkit\Access\Console;

/** @internal */
final readonly class GeneratedPerimeters
{
    /** @param  list<string>  $classes */
    public function __construct(private array $classes) {}

    /** @return list<string> */
    public function buildable(): array
    {
        return array_values(array_filter($this->classes, fn (string $class): bool => (new GeneratedPerimeter($class))->isBuildable()));
    }

    /** @return list<string> */
    public function warnings(): array
    {
        $warnings = [];

        foreach ($this->classes as $class) {
            $warning = (new GeneratedPerimeter($class))->skippedWarning();

            if ($warning !== null) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }
}
