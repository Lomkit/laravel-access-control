<?php

namespace Lomkit\Access\Console\Check\Checks;

use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Notes\InheritedMemoryPerimeters;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use ReflectionMethod;

/** @internal */
final class MemorySpreadCheck
{
    private const string PERIMETERS = 'perimeters';

    /** @param  list<Control>  $controls */
    public function run(Findings $findings, array $controls): void
    {
        $inherited = [];
        $perimeters = [];

        foreach ($controls as $control) {
            $base = (new ReflectionMethod($control, self::PERIMETERS))->getDeclaringClass()->getName();
            $memory = $this->memoryPerimeters($control);

            if ($base !== $control::class && $base !== Control::class && $memory !== []) {
                $inherited[$base][] = $control::class;
                $perimeters[$base] = $memory;
            }
        }

        foreach ($inherited as $base => $subclasses) {
            if (count($subclasses) > 1) {
                $findings->note(new InheritedMemoryPerimeters($base, $perimeters[$base], $subclasses));
            }
        }
    }

    /** @return list<string> */
    private function memoryPerimeters(Control $control): array
    {
        $memory = [];

        foreach ($control->declaration()->perimeters()->entries() as $entry) {
            foreach ($entry instanceof Levels ? $entry->all() : [$entry] as $perimeter) {
                if ($this->checksRowsInMemory($perimeter)) {
                    $memory[] = $perimeter::class;
                }
            }
        }

        return array_values(array_unique($memory));
    }

    private function checksRowsInMemory(Perimeter $perimeter): bool
    {
        return $perimeter->checksInMemory() && ! $perimeter->isUnrestricted();
    }
}
