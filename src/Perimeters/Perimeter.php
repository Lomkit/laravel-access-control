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
    public int $priority;

    /**
     * The name of the perimeter.
     *
     * @var string
     */
    public string $name;

    /**
     * The perimeter's registration status.
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * Set the priority of the perimeter.
     *
     * @param  int $priority
     * @return Perimeter
     */
    public function priority(int $priority): Perimeter
    {
        return tap($this, function () use ($priority) {
            $this->priority = $priority;
        });
    }

    /**
     * Set the name of the perimeter.
     *
     * @param  string $name
     * @return Perimeter
     */
    public function name(string $name): Perimeter
    {
        return tap($this, function () use ($name) {
            $this->name = Str::camel($name);
        });
    }

    /**
     * Determine if the perimeter matches a given name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function matches(string $name)
    {
        return $name === $this->name;
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