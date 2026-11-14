<?php

namespace Lomkit\Access;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Lomkit\Access\Exceptions\InvalidSearchEngineException;
use Lomkit\Access\Policies\AutomaticPolicies;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\SearchField;
use Lomkit\Access\Search\TrustedSearchEngines;

/**
 * @method static \Lomkit\Access\Controls\Control controlFor(\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model> $model)
 * @method static bool hasControl(\Illuminate\Database\Eloquent\Model|class-string<\Illuminate\Database\Eloquent\Model> $model)
 * @method static \Lomkit\Access\Controls\Control control(class-string<\Lomkit\Access\Controls\Control> $control)
 * @method static void discoverControlsIn(list<string> $paths)
 *
 * @see ControlRegistry
 */
final class Access extends Facade
{
    public static function registerPolicies(): void
    {
        Container::getInstance()
            ->make(AutomaticPolicies::class)
            ->register();
    }

    /** @return list<string> */
    public static function searchFilterables(): array
    {
        return SearchField::names();
    }

    /** @throws InvalidSearchEngineException When the class is not a Scout engine. */
    public static function trustSearchEngine(string $engine): void
    {
        Container::getInstance()
            ->make(TrustedSearchEngines::class)
            ->trust($engine);
    }

    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return ControlRegistry::class;
    }
}
