<?php

namespace Lomkit\Access\Perimeters;

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
            $this->name = $name;
        });
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