<?php

namespace Lomkit\Access\Policies;

use Closure;
use Illuminate\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Registry\ControlRegistry;
use ReflectionClass;

/** @internal */
final readonly class AutomaticPolicies
{
    public function __construct(
        private Container $container,
        private ControlRegistry $registry,
    ) {}

    public function register(): void
    {
        $gate = $this->container->make(GateContract::class);

        $conventional = ConventionalPolicyNames::of($gate, $this->container);

        $gate->guessPolicyNamesUsing(fn (string $class): array => $this->guess($gate, $conventional, $class));
    }

    public static function registeredIn(Gate $gate): bool
    {
        return ConventionalPolicyNames::guessedIn($gate, self::class);
    }

    /**
     * @param  Closure(string): array<array-key, mixed>  $conventional
     * @return array<array-key, mixed>
     */
    private function guess(Gate $gate, Closure $conventional, string $class): array
    {
        $names = $conventional($class);

        if (! is_subclass_of($class, Model::class) || $this->namesAPolicy($names) || $this->inheritsAPolicy($gate, $class) || ! $this->registry->hasControl($class)) {
            return $names;
        }

        $this->container->bind(AutomaticPolicy::class, fn (): AutomaticPolicy => new AutomaticPolicy($class));

        return [AutomaticPolicy::class];
    }

    /** @param  array<array-key, mixed>  $names */
    private function namesAPolicy(array $names): bool
    {
        foreach ($names as $name) {
            if (is_string($name) && class_exists($name)) {
                return true;
            }
        }

        return false;
    }

    private function inheritsAPolicy(Gate $gate, string $class): bool
    {
        foreach (array_keys($gate->policies()) as $model) {
            if (is_subclass_of($class, $model)) {
                return true;
            }
        }

        for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
            if ((new ReflectionClass($parent))->getAttributes(UsePolicy::class) !== []) {
                return true;
            }
        }

        return false;
    }
}
