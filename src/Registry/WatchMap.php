<?php

namespace Lomkit\Access\Registry;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final readonly class WatchMap
{
    private const string WATCHERS = 'watchers';

    private const string VERSIONS = 'versions';

    /**
     * @param  array<string, list<string>>  $watchers
     * @param  array<string, int>  $versions
     */
    private function __construct(
        private array $watchers,
        private array $versions,
    ) {}

    public static function of(ControlRegistry $registry, ControlMap $controls): self
    {
        $watchers = [];
        $versions = [];

        foreach ($controls->toArray() as $model => $control) {
            if (ConcreteControl::is($control)) {
                $versions[$control] = ControlVersion::of($control);
            }

            if (! self::watchable($model, $control)) {
                continue;
            }

            $rules = $registry->control($control)->searchRules();

            foreach ($rules->watchedModels() as $watched) {
                $watchers[$watched][] = $control;
            }
        }

        return new self($watchers, $versions);
    }

    public static function fromCache(mixed $cached): ?self
    {
        if (! is_array($cached) || ! is_array($cached[self::WATCHERS] ?? null) || ! is_array($cached[self::VERSIONS] ?? null)) {
            return null;
        }

        $watchers = [];
        $versions = [];

        foreach ($cached[self::WATCHERS] as $model => $controls) {
            if (is_string($model) && is_array($controls)) {
                $watchers[$model] = array_values(array_filter($controls, is_string(...)));
            }
        }

        foreach ($cached[self::VERSIONS] as $control => $version) {
            if (is_string($control) && is_int($version)) {
                $versions[$control] = $version;
            }
        }

        return new self($watchers, $versions);
    }

    public function isCurrent(): bool
    {
        foreach ($this->versions as $control => $version) {
            if (! class_exists($control) || ControlVersion::of($control) !== $version) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function controlsWatching(Model $instance): array
    {
        $controls = [];

        foreach ([$instance::class, ...array_values(class_parents($instance))] as $class) {
            array_push($controls, ...($this->watchers[$class] ?? []));
        }

        return array_values(array_unique($controls));
    }

    /** @param  list<string>  $watched */
    public function covers(string $control, array $watched): bool
    {
        foreach ($watched as $model) {
            if (! in_array($control, $this->watchers[$model] ?? [], true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{watchers: array<string, list<string>>, versions: array<string, int>} */
    public function toArray(): array
    {
        return [self::WATCHERS => $this->watchers, self::VERSIONS => $this->versions];
    }

    /** @phpstan-assert-if-true class-string<Control> $control */
    private static function watchable(string $model, string $control): bool
    {
        return class_exists($model)
            && ConcreteControl::is($control)
            && DeclaredModel::of($control) === $model
            && UsesControlledSearchable::check($model);
    }
}
