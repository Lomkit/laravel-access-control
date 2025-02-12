<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Throwable;

class Control
{
    /**
     * The control name resolver.
     *
     * @var callable
     */
    protected static $controlNameResolver;

    /**
     * The default namespace where control reside.
     *
     * @var string
     */
    public static $namespace = 'App\\Access\\Controls\\';

    /**
     * Get the perimeters for the current control
     *
     * @return array
     */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * Specify the callback that should be invoked to guess control names.
     *
     * @param  callable(class-string<\Illuminate\Database\Eloquent\Model>): class-string<\Lomkit\Access\Controls\Control>  $callback
     * @return void
     */
    public static function guessControlNamesUsing(callable $callback)
    {
        static::$controlNameResolver = $callback;
    }


    /**
     * Get a new control instance for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TClass>  $modelName
     * @return \Lomkit\Access\Controls\Control<TClass>
     */
    public static function controlForModel(string $modelName)
    {
        $control = static::resolveControlName($modelName);

        return $control::new();
    }
    //@TODO: new ClientPerimeter($queryCallback, $policyCallback) ?
    // @TODO: shouldCallback déjà définie ?

    /**
     * Get a new control instance for the given attributes.
     *
     * @return static
     */
    public static function new()
    {
        return (new static);
    }

    /**
     * Get the control name for the given model name.
     *
     * @template TClass of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TClass>  $modelName
     * @return class-string<\Lomkit\Access\Controls\Control<TClass>>
     */
    public static function resolveControlName(string $modelName)
    {
        $resolver = static::$controlNameResolver ?? function (string $modelName) {
            $appNamespace = static::appNamespace();

            $modelName = Str::startsWith($modelName, $appNamespace.'Models\\')
                ? Str::after($modelName, $appNamespace.'Models\\')
                : Str::after($modelName, $appNamespace);

            return static::$namespace.$modelName.'Control';
        };

        return $resolver($modelName);
    }

    /**
     * Get the application namespace for the application.
     *
     * @return string
     */
    protected static function appNamespace()
    {
        try {
            return Container::getInstance()
                ->make(Application::class)
                ->getNamespace();
        } catch (Throwable) {
            return 'App\\';
        }
    }
}