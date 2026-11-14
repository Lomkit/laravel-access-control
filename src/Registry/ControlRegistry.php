<?php

namespace Lomkit\Access\Registry;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Exceptions\InvalidControlException;
use Lomkit\Access\Exceptions\MissingControlException;

final class ControlRegistry
{
    private ?ControlMap $discovered = null;

    /** @var array<string, class-string<Control>|null> */
    private array $links = [];

    /** @var array<class-string<Control>, Control> */
    private array $controls = [];

    public function __construct(
        private readonly Container $container,
        private readonly ControlDiscovery $discovery,
        private readonly DiscoveryCache $cache,
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
     */
    public function control(string $control): Control
    {
        $instance = $this->controls[$control] ?? $this->resolve($control);

        if (! $instance instanceof $control) {
            throw InvalidControlException::resolvedAs($control, $instance);
        }

        return $this->controls[$control] = $instance;
    }

    /** @param  list<string>  $paths */
    public function discoverControlsIn(array $paths): void
    {
        $this->discovery->within($paths);

        $this->discovered = null;
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
        $this->discovered ??= $this->cache->exists()
            ? $this->cache->read()
            : $this->discovery->map();

        $control = $this->discovered->find($model);

        if ($control === null || ! ConcreteControl::is($control)) {
            return null;
        }

        return DeclaredModel::of($control) === $model ? $control : null;
    }
}
