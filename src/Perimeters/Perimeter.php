<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Http\Request;
use Illuminate\Routing\Matching\MethodValidator;
use Illuminate\Support\Str;

class Perimeter
{
    /**
     * The priority of the perimeter.
     *
     * @var int
     */
    protected int $priority;

    /**
     * The name of the perimeter.
     *
     * @var string
     */
    protected string $name;

    /**
     * The perimeter's registration status.
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * Set the priority of the perimeter.
     *
     * @return int
     */
    public function priority(): int
    {
        return $this->priority ?? 1;
    }

    /**
     * Set the name of the perimeter.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name ?? Str::of((new \ReflectionClass($this))->getShortName())->beforeLast('Perimeter')->camel()->toString();
    }

    /**
     * Determine if the perimeter matches a given name.
     *
     * @param string $name
     * @return bool
     */
    public function matches(string $name)
    {
        return $name === $this->name();
    }

    /**
     * Register the perimeter.
     *
     * @return PerimeterCollection
     */
    public function register(): PerimeterCollection
    {
        $this->registered = true;

        return app(Perimeters::class)
            ->addPerimeter($this);
    }

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        if (! $this->registered) {
            $this->register();
        }
    }
}