<?php

namespace Lomkit\Access\Discovery;

use Illuminate\Contracts\Foundation\Application;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Exceptions\InvalidControlModelException;
use Lomkit\Access\Registry\ConcreteControl;
use Lomkit\Access\Registry\ControlMap;
use Lomkit\Access\Registry\DeclaredModel;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

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
     * @throws DuplicateControlException When two discovered controls declare the same model.
     * @throws InvalidControlModelException When a discovered control declares no Eloquent model.
     */
    public function map(): ControlMap
    {
        $map = new ControlMap;

        foreach ($this->classes() as $control) {
            $map = $map->with(DeclaredModel::of($control), $control);
        }

        return $map;
    }

    /** @return list<class-string<Control>> */
    private function classes(): array
    {
        $classes = [];

        foreach ($this->directories() as $directory) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->sortByName()
                ->in($directory);

            foreach ($files as $file) {
                $class = $this->classFromFile($file);

                if (ConcreteControl::is($class)) {
                    $classes[$class] = $class;
                }
            }
        }

        return array_values($classes);
    }

    /** @return list<string> */
    private function directories(): array
    {
        $directories = [];

        foreach ($this->paths as $path) {
            foreach (glob($path, GLOB_ONLYDIR) ?: [] as $directory) {
                $directories[] = $directory;
            }
        }

        return $directories;
    }

    private function classFromFile(SplFileInfo $file): string
    {
        $contents = (string) file_get_contents($file->getRealPath());

        $namespace = preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $contents, $matches) === 1
            ? $matches[1].'\\'
            : '';

        return $namespace.$file->getBasename('.php');
    }
}
