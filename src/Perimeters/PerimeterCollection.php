<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Support\Arr;

class PerimeterCollection
{
    /**
     * An array of the perimeters keyed by priority.
     *
     * @var array
     */
    protected array $perimeters = [];

    /**
     * A flattened array of all of the perimeters.
     *
     * @var Perimeter[]
     */
    protected array $allPerimeters = [];

    /**
     * Add a Perimeter instance to the collection.
     *
     * @param Perimeter $perimeter
     *
     * @return Perimeter
     */
    public function add(Perimeter $perimeter): PerimeterCollection
    {
        $this->addToCollections($perimeter);

        return $this;
    }

    /**
     * Add the given perimeter to the arrays of perimeters.
     *
     * @param Perimeter $perimeter
     *
     * @return void
     */
    protected function addToCollections(Perimeter $perimeter)
    {
        $this->perimeters[$perimeter->priority][] = $perimeter;

        $this->allPerimeters = collect($this->allPerimeters)
            ->push($perimeter)
            ->sortBy('priority')
            ->all();
    }

    /**
     * Find the first perimeter matching a given name.
     *
     * @param string $name
     *
     * @throws \RuntimeException
     *
     * @return Perimeter
     */
    public function match(string $name)
    {
        $perimeters = $this->get();

        $perimeter = $this->matchAgainstPerimeters($perimeters, $name);

        return $this->handleMatchedPerimeter($name, $perimeter);
    }

    /**
     * Determine if a perimeter in the array matches the name.
     *
     * @param Perimeter[]              $perimeters
     * @param \Illuminate\Http\Request $request
     *
     * @return Perimeter|null
     */
    protected function matchAgainstPerimeters(array $perimeters, string $name)
    {
        return collect($perimeters)->first(
            fn (Perimeter $perimeter) => $perimeter->matches($name)
        );
    }

    /**
     * Handle the matched perimeter.
     *
     * @param string         $name
     * @param Perimeter|null $perimeter
     *
     * @throws \RuntimeException
     *
     * @return Perimeter
     */
    protected function handleMatchedPerimeter(string $name, $perimeter)
    {
        if (!is_null($perimeter)) {
            return $perimeter;
        }

        throw new \RuntimeException(sprintf(
            'The perimeter %s could not be found.',
            $name
        ));
    }

    /**
     * Get perimeters from the collection by priority.
     *
     * @param int|null $priority
     *
     * @return Perimeter[]
     */
    public function get(int $priority = null)
    {
        return is_null($priority) ? $this->getPerimeters() : Arr::get($this->perimeters, $priority, []);
    }

    /**
     * Get all of the perimeters in the collection.
     *
     * @return Perimeter[]
     */
    public function getPerimeters()
    {
        return array_values($this->allPerimeters);
    }
}
