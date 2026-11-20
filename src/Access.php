<?php

namespace Lomkit\Access;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Lomkit\Access\Decisions\DecisionContextResolver;
use Lomkit\Access\Decisions\DecisionFlushes;
use Lomkit\Access\Decisions\Preloader;
use Lomkit\Access\Decisions\RowContextSwitch;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidBatchRowException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Policies\AutomaticPolicies;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Search\AccessSearchMethods;

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
    use AccessSearchMethods;

    public static function registerPolicies(): void
    {
        Container::getInstance()
            ->make(AutomaticPolicies::class)
            ->register();
    }

    /** @param  Closure(): array<string, string|int|bool|null>  $resolver */
    public static function decisionContextUsing(Closure $resolver): void
    {
        Container::getInstance()
            ->make(DecisionContextResolver::class)
            ->use($resolver);
    }

    /**
     * @param  Closure(): list<int|string>  $values
     * @param  Closure(int|string, Closure(): mixed): mixed  $within
     */
    public static function rowContextUsing(Closure $values, Closure $within): void
    {
        Container::getInstance()
            ->make(RowContextSwitch::class)
            ->use($values, $within);
    }

    /**
     * @param  iterable<Model>  $rows
     *
     * @throws InvalidBatchRowException When a row is not an Eloquent model.
     * @throws MissingControlException When no control is registered for the model of a row.
     * @throws InvalidAbilityException When the control cannot map the ability.
     */
    public static function preload(Model $reader, Ability|string $ability, iterable $rows): void
    {
        Container::getInstance()
            ->make(Preloader::class)
            ->preload($reader, $ability, $rows);
    }

    public static function forgetDecisions(): void
    {
        Container::getInstance()
            ->make(DecisionFlushes::class)
            ->flush();
    }

    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return ControlRegistry::class;
    }
}
