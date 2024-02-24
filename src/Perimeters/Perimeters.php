<?php

namespace Lomkit\Access\Perimeters;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class Perimeters
{
    /**
     * The perimeter collection instance.
     *
     * @var PerimeterCollection
     */
    protected PerimeterCollection $perimeters;

    /**
     * Create a new Perimeters instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->perimeters = new PerimeterCollection();
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param Perimeter $perimeter
     *
     * @return PerimeterCollection
     */
    public function addPerimeter(Perimeter $perimeter): PerimeterCollection
    {
        return $this->perimeters->add($perimeter);
    }

    /**
     * Find the perimeter matching a given name.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Perimeter
     */
    public function findPerimeter(string $name)
    {
        $perimeter = $this->perimeters->match($name);

        return $perimeter;
    }

    /**
     * Get the plain perimeters.
     *
     * @return Perimeter[]
     */
    public function getPerimeters()
    {
        return $this->perimeters->getPerimeters();
    }

    /**
     * Register all the perimeter classes in the given directory.
     *
     * @param string $directory
     *
     * @return void
     */
    public function perimetersIn($directory)
    {
        $namespace = app()->getNamespace();

        foreach ((new Finder())->in($directory)->files() as $perimeter) {
            $perimeter = $namespace.str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($perimeter->getPathname(), app_path().DIRECTORY_SEPARATOR)
            );

            if (
                is_subclass_of($perimeter, \Lomkit\Access\Perimeters\Perimeter::class) &&
                !(new ReflectionClass($perimeter))->isAbstract()
            ) {
                $this->addPerimeter($perimeter);
            }
        }
    }
}
