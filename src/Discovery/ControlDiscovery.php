<?php

namespace Lomkit\Access\Discovery;

use Illuminate\Contracts\Foundation\Application;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use Lomkit\Access\Registry\ConcreteControl;
use Lomkit\Access\Registry\ControlMap;
use Lomkit\Access\Registry\DeclaredModel;

final class ControlDiscovery
{
    /** @var list<string> */
    private array $paths;

    public function __construct(Application $app)
    {
        $this->paths = [$app->path('Access/Controls')];
    }

    /** @param  list<string>  $paths */
    public function within(array $paths): void
    {
        $this->paths = $paths;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @throws DuplicateControlException When two discovered controls declare the same model.
     * @throws InvalidControlModelException When a discovered control declares no Eloquent model.
     */
    public function map(): ControlMap
    {
        $map = new ControlMap;

        foreach ($this->controls() as $control) {
            $map = $map->with(DeclaredModel::of($control), $control);
        }

        return $map;
    }

    /**
     * @internal
     *
     * @return list<class-string<Control>>
     */
    public function controls(): array
    {
        $classes = [];

        foreach (ClassFiles::in($this->paths) as $class) {
            if (ConcreteControl::is($class)) {
                $classes[$class] = $class;
            }
        }

        return array_values($classes);
    }
}
