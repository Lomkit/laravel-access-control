<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Support\Str;

class Perimeter
{
    /**
     * The priority of the perimeter.
     *
     * @var int
     */
    public int $priority;

    /**
     * The name of the perimeter.
     *
     * @var string
     */
    public string $name;

    /**
     * Determine if the perimeter is final.
     *
     * @var bool
     */
    public bool $final;

    /**
     * Get the priority of the perimeter.
     *
     * @return int
     */
    public function priority(): int
    {
        return $this->priority ?? 1;
    }

    /**
     * Get the name of the perimeter.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name ?? Str::of((new \ReflectionClass($this))->getShortName())->beforeLast('Perimeter')->camel()->toString();
    }

    /**
     * Get the final perimeter status.
     *
     * @return int
     */
    public function final(): int
    {
        return $this->final ?? true;
    }

    /**
     * Determine if the perimeter matches a given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function matches(string $name)
    {
        return $name === $this->name();
    }
}
