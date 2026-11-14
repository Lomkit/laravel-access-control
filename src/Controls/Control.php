<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\AbilityParameterTypeException;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Perimeters\AbilityParameter;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Registry\ControlRegistry;
use Throwable;

class Control
{
    use ControlsQueries;
    use ControlsScoutQueries;

    /** @var class-string<Model> */
    protected string $model;

    /** @var array<Perimeter>|null */
    private ?array $resolvedPerimeters = null;

    /** @param  class-string<Model>  $model */
    public function isModel(string $model): bool
    {
        return $model === $this->model;
    }

    /** @return class-string<Model> */
    public function getModel(): string
    {
        return $this->model;
    }

    /** @return array<Perimeter> */
    protected function perimeters(): array
    {
        return [];
    }

    /**
     * @return array<Perimeter>
     *
     * @throws AbilityParameterTypeException When a perimeter overrides applyAllowedCallback() without typing its ability to receive an Ability.
     */
    public function getPerimeters(): array
    {
        return $this->resolvedPerimeters ??= $this->typedPerimeters($this->perimeters());
    }

    /**
     * @throws InvalidAbilityException When the policy method is not a valid ability name.
     */
    public function abilityFor(string $policyMethod): Ability
    {
        return match ($policyMethod) {
            'viewAny', 'view' => Ability::view(),
            'create' => Ability::create(),
            'update' => Ability::update(),
            'delete' => Ability::delete(),
            'restore' => Ability::restore(),
            'forceDelete' => Ability::forceDelete(),
            default => Ability::of($policyMethod),
        };
    }

    public function applies(Model $user, Ability $ability, Model $model): bool
    {
        $perimeters = $this->applicablePerimeters($user, $ability);

        if ($perimeters === []) {
            return false;
        }

        if (! $model->exists || $this->reachesEveryRow($perimeters)) {
            return true;
        }

        foreach ($perimeters as $perimeter) {
            if ($perimeter->applyShouldCallback($user, $model)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Perimeter> */
    final protected function applicablePerimeters(Model $user, Ability $ability): array
    {
        $applicable = [];

        foreach ($this->getPerimeters() as $perimeter) {
            if (! $perimeter->applyAllowedCallback($user, $ability)) {
                continue;
            }

            $applicable[] = $perimeter;

            if (! $perimeter->overlays()) {
                break;
            }
        }

        return $applicable;
    }

    /** @param  list<Perimeter>  $perimeters */
    final protected function reachesEveryRow(array $perimeters): bool
    {
        foreach ($perimeters as $perimeter) {
            if ($perimeter->isUnrestricted()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Perimeter>  $perimeters
     * @return array<Perimeter>
     */
    private function typedPerimeters(array $perimeters): array
    {
        foreach ($perimeters as $perimeter) {
            AbilityParameter::ensureTypedInHookOf($perimeter);
        }

        return $perimeters;
    }

    public static function new(): static
    {
        return Container::getInstance()
            ->make(ControlRegistry::class)
            ->control(static::class);
    }

    protected static function appNamespace(): string
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
