<?php

namespace Lomkit\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lomkit\Access\Controls\Control;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class Access
{

    /**
     * The default path where control reside.
     *
     * @var array
     */
    public static array $controlDiscoveryPaths;

    /**
     * The registered controls
     * @var Control[]
     */
    protected static array $controls;

    /**
     * Add multiple control to access.
     *
     * @param Control[] $controls
     * @return Access
     */
    public function addControls(array $controls): static
    {
        foreach ($controls as $control) {
            $this->addControl($control);
        }

        return $this;
    }

    /**
     * Add a control to access.
     *
     * @param Control $control
     *
     * @return Access
     */
    public function addControl(Control $control): self
    {
        static::$controls[class_basename($control)] = $control;

        return $this;
    }

    /**
     * Get the control instance for the given model
     *
     * @param Model|class-string<Model> $model
     * @return Control|null
     */
    public static function controlForModel(Model|string $model): ?Control
    {
        if (!is_string($model)) {
            $model = $model::class;
        }

        foreach (static::$controls as $control) {
            if ($control->isModel($model)) {
                return $control;
            }
        }

        return null;
    }

    /**
     * Discover controls for a given path
     *
     * @var string[] $paths
     */
    public function discoverControls(array $paths): self
    {
        (new Collection($paths))
            ->flatMap(function ($directory) {
                return glob($directory, GLOB_ONLYDIR);
            })
            ->reject(function ($directory) {
                return ! is_dir($directory);
            })
            ->each(function ($directory) {
                $controls = Finder::create()->files()->in($directory);

                foreach ($controls as $control) {
                    try {
                        $control = new ReflectionClass(
                            static::classFromFile($control, base_path())
                        );
                    } catch (ReflectionException) {
                        continue;
                    }

                    if (! $control->isInstantiable()) {
                        continue;
                    }

                    $this->addControl($control->newInstance());
                }
            });

        return $this;
    }

    /**
     * Get the control directories that should be used to discover controls.
     *
     * @return array
     */
    public function discoverControlsWithin(): array
    {
        return static::$controlDiscoveryPaths ?? [
            app()->path('Access/Controls'),
        ];
    }

    /**
     * Extract the class name from the given file path.
     *
     * @param  SplFileInfo  $file
     * @param  string  $basePath
     * @return string
     */
    protected function classFromFile(SplFileInfo $file, string $basePath)
    {

        $class = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        return ucfirst(Str::camel(str_replace(
            [DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())).'\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        )));
    }
}