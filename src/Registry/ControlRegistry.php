<?php

namespace Lomkit\Access\Registry;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\InvalidControlException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Search\UsesControlledSearchable;

final class ControlRegistry
{
    private ?ControlMap $discovered = null;

    private ?WatchMap $watched = null;

    /** @var array<string, class-string<Control>|null> */
    private array $links = [];

    /** @var array<class-string<Control>, Control> */
    private array $controls = [];

    public function __construct(
        private readonly Container $container,
        private readonly ControlDiscovery $discovery,
        private readonly DiscoveryCache $cache,
        private readonly PerimeterPurity $purity,
    ) {}

    /**
     * @param  Model|class-string<Model>  $model
     *
     * @throws MissingControlException When no control is registered for the model.
     */
    public function controlFor(Model|string $model): Control
    {
        $model = $model instanceof Model ? $model::class : $model;

        $control = $this->linkFor($model) ?? throw MissingControlException::forModel($model);

        return $this->control($control);
    }

    /** @param  Model|class-string<Model>  $model */
    public function hasControl(Model|string $model): bool
    {
        return $this->linkFor($model instanceof Model ? $model::class : $model) !== null;
    }

    /**
     * @template TControl of Control
     *
     * @param  class-string<TControl>  $control
     * @return TControl
     *
     * @throws InvalidControlException When the class is not a control, or the container resolves another class.
     * @throws ImpurePerimetersException When the control builds perimeters of another shape than the ones it built first, in debug mode and in tests.
     */
    public function control(string $control): Control
    {
        $instance = $this->controls[$control] ?? $this->resolve($control);

        if (! $instance instanceof $control) {
            throw InvalidControlException::resolvedAs($control, $instance);
        }

        $this->purity->check($instance);

        if (! array_key_exists($control, $this->controls)) {
            $this->ensureWatchable($control, $instance);
        }

        return $this->controls[$control] = $instance;
    }

    /**
     * @internal
     *
     * @return list<string>
     */
    public function controlsWatching(Model $instance): array
    {
        $this->watched ??= $this->currentCachedWatchMap() ?? $this->watchMap();

        return $this->watched->controlsWatching($instance);
    }

    /** @internal */
    public function watchMap(): WatchMap
    {
        return WatchMap::of($this, $this->discovered());
    }

    /** @param  list<string>  $paths */
    public function discoverControlsIn(array $paths): void
    {
        $this->discovery->within($paths);

        $this->discovered = null;
        $this->watched = null;
        $this->links = [];
    }

    /**
     * @return class-string<Control>|null
     *
     * @throws DuplicateControlException When the model declares a control and another control is discovered for it.
     */
    private function linkFor(string $model): ?string
    {
        if (array_key_exists($model, $this->links)) {
            return $this->links[$model];
        }

        if (! is_subclass_of($model, Model::class)) {
            return $this->links[$model] = null;
        }

        $declared = DeclaredControl::of($model);
        $discovered = $this->discoveredFor($model);

        if ($declared !== null && $discovered !== null && $declared !== $discovered) {
            throw DuplicateControlException::forModel($model, $declared, $discovered);
        }

        return $this->links[$model] = $declared ?? $discovered;
    }

    private function resolve(string $control): mixed
    {
        if (! ConcreteControl::is($control)) {
            throw InvalidControlException::notAControl($control);
        }

        DeclaredModel::of($control);

        return $this->container->make($control);
    }

    /**
     * @param  class-string<Model>  $model
     * @return class-string<Control>|null
     */
    private function discoveredFor(string $model): ?string
    {
        $control = $this->discovered()->find($model);

        if ($control === null || ! ConcreteControl::is($control)) {
            return null;
        }

        return DeclaredModel::of($control) === $model ? $control : null;
    }

    private function discovered(): ControlMap
    {
        return $this->discovered ??= $this->cache->exists()
            ? $this->cache->read()
            : $this->discovery->map();
    }

    /** @throws UnwatchableReindexException When the control reindexes for a model that a fresh process would not watch. */
    private function ensureWatchable(string $control, Control $instance): void
    {
        $watched = $instance->searchRules()->watchedModels();

        if ($watched === []) {
            return;
        }

        if (! in_array($control, $this->discovered()->toArray(), true)) {
            throw UnwatchableReindexException::undiscovered($control);
        }

        if (! UsesControlledSearchable::check($instance->getModel())) {
            throw UnwatchableReindexException::unsearchable($control, $instance->getModel());
        }

        if ($this->currentCachedWatchMap()?->covers($control, $watched) === false) {
            throw UnwatchableReindexException::staleCache($control);
        }
    }

    private function currentCachedWatchMap(): ?WatchMap
    {
        $cached = $this->cache->readWatched();

        return $cached?->isCurrent() === true ? $cached : null;
    }
}
