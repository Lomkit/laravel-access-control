<?php

namespace Lomkit\Access\Registry;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final readonly class WatchMap
{
    /** @param  array<string, list<string>>  $watchers */
    private function __construct(private array $watchers) {}

    public static function of(ControlRegistry $registry, ControlMap $controls): self
    {
        return self::from($controls, $registry->control(...));
    }

    /** @param  Closure(class-string<Control>): Control  $resolve */
    public static function from(ControlMap $controls, Closure $resolve): self
    {
        $watchers = [];

        foreach ($controls->toArray() as $model => $control) {
            if (! self::watchable($model, $control)) {
                continue;
            }

            $rules = $resolve($control)->searchRules();

            foreach ($rules->freshness()->watchedModels() as $watched) {
                $watchers[$watched][] = $control;
            }
        }

        return new self($watchers);
    }

    public static function fromCache(mixed $cached): ?self
    {
        if (! is_array($cached)) {
            return null;
        }

        $watchers = [];

        foreach ($cached as $model => $controls) {
            if (is_string($model) && is_array($controls)) {
                $watchers[$model] = array_values(array_filter($controls, is_string(...)));
            }
        }

        return new self($watchers);
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

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->watchers;
    }

    /** @phpstan-assert-if-true class-string<Control> $control */
    public static function watchable(string $model, string $control): bool
    {
        return class_exists($model)
            && ConcreteControl::is($control)
            && DeclaredModel::of($control) === $model
            && UsesControlledSearchable::check($model);
    }
}
