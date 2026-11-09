<?php

namespace Lomkit\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
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
     * The paths where controls are discovered.
     *
     * @var string[]|null
     */
    public static ?array $controlDiscoveryPaths = null;

    /**
     * The registered controls, keyed by their class name.
     *
     * @var array<class-string<Control>, Control>
     */
    protected static array $controls = [];

    /**
     * Whether the discovery already ran in this process.
     */
    protected static bool $discovered = false;

    /**
     * Add multiple controls to access.
     *
     * @param  Control[]  $controls
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
     */
    public function addControl(Control $control): static
    {
        static::$controls[$control::class] = $control;

        return $this;
    }

    /**
     * Get the registered instance of the given control class, registering it when needed.
     *
     * @param  class-string<Control>  $control
     */
    public static function control(string $control): Control
    {
        static::ensureDiscovered();

        return static::$controls[$control] ??= new $control;
    }

    /**
     * Get the control instance for the given model.
     *
     * @param  Model|class-string<Model>  $model
     */
    public static function controlForModel(Model|string $model): ?Control
    {
        static::ensureDiscovered();

        if (! is_string($model)) {
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
     * Discover controls in the given paths.
     *
     * @param  string[]  $paths
     */
    public function discoverControls(array $paths): static
    {
        foreach (static::discoverControlClasses($paths) as $control) {
            $this->addControl(new $control);
        }

        return $this;
    }

    /**
     * Find the instantiable control classes in the given paths.
     *
     * @param  string[]  $paths
     * @return list<class-string<Control>>
     */
    public static function discoverControlClasses(array $paths): array
    {
        return (new Collection($paths))
            ->flatMap(fn (string $directory) => glob($directory, GLOB_ONLYDIR) ?: [])
            ->filter(fn (string $directory) => is_dir($directory))
            ->flatMap(fn (string $directory) => iterator_to_array(Finder::create()->files()->name('*.php')->in($directory), false))
            ->map(function (SplFileInfo $file) {
                try {
                    $class = new ReflectionClass(static::classFromFile($file));
                } catch (ReflectionException) {
                    return null;
                }

                return $class->isInstantiable() && $class->isSubclassOf(Control::class) ? $class->getName() : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the control directories that should be used to discover controls.
     *
     * @return string[]
     */
    public function discoverControlsWithin(): array
    {
        return static::$controlDiscoveryPaths ?? [
            app()->path('Access/Controls'),
        ];
    }

    /**
     * Get the path of the discovered controls cache file.
     */
    public static function cachePath(): string
    {
        return app()->bootstrapPath('cache/access-controls.php');
    }

    /**
     * Discover the controls once per process, from the cache file when it exists.
     */
    protected static function ensureDiscovered(): void
    {
        if (static::$discovered) {
            return;
        }

        static::$discovered = true;

        $access = new static;

        $classes = is_file(static::cachePath())
            ? require static::cachePath()
            : static::discoverControlClasses($access->discoverControlsWithin());

        foreach ($classes as $control) {
            static::$controls[$control] ??= new $control;
        }
    }

    /**
     * Forget the registered controls and run the discovery again on next use.
     */
    public static function flush(): void
    {
        static::$controls = [];
        static::$discovered = false;
    }

    /**
     * Write the discovered controls to the cache file.
     */
    public static function cache(): void
    {
        $classes = static::discoverControlClasses((new static)->discoverControlsWithin());

        (new Filesystem)->put(
            static::cachePath(),
            '<?php return '.var_export($classes, true).';'.PHP_EOL
        );
    }

    /**
     * Extract the class name from the given file, reading its namespace declaration.
     */
    protected static function classFromFile(SplFileInfo $file): string
    {
        $contents = (string) file_get_contents($file->getRealPath());

        $namespace = preg_match('/^\s*namespace\s+([^;\s]+)\s*;/m', $contents, $matches) === 1
            ? $matches[1].'\\'
            : '';

        return $namespace.Str::replaceLast('.php', '', $file->getFilename());
    }
}
