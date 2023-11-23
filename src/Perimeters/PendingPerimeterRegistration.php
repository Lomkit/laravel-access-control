<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Support\Traits\Conditionable;

class PendingPerimeterRegistration
{
    use Conditionable;

    /**
     * The priority linked to the perimeter. The lower is the highest priority
     *
     * @var Perimeter
     */
    protected Perimeter $perimeter;

    /**
     * The priority of the perimeter.
     *
     * @var int
     */
    protected int $priority;

    /**
     * The name of the scope.
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
     * Create a new pending perimeter instance.
     *
     * @param  Perimeter $perimeter
     * @return void
     */
    public function __construct(Perimeter $perimeter)
    {
        $this->perimeter = $perimeter;
    }

    /**
     * Set the priority of the perimeter.
     *
     * @param  int $priority
     * @return PendingPerimeterRegistration
     */
    public function priority(int $priority): PendingPerimeterRegistration
    {
        return tap($this, function () use ($priority) {
            $this->priority = $priority;
        });
    }

    /**
     * Set the name of the perimeter.
     *
     * @param  string $name
     * @return PendingPerimeterRegistration
     */
    public function name(string $name): PendingPerimeterRegistration
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

        return $this->perimeter->register(
            $this->name, $this->priority
        );
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