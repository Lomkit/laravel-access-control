<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Registry\ControlRegistry;

class Control
{
    use ControlsQueries;
    use ControlsScoutQueries;

    /** @var class-string<Model> */
    protected string $model;

    private ?PerimeterList $declaredPerimeters = null;

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
        $context = AccessContext::of($user, $ability);
        $perimeters = $this->perimetersApplicableTo($context);

        if ($perimeters === []) {
            return false;
        }

        if (! $model->exists || $this->reachesEveryRow($perimeters)) {
            return true;
        }

        foreach ($perimeters as $perimeter) {
            if ($perimeter->applyShouldCallback($model, $context)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Perimeter> */
    final protected function applicablePerimeters(Model $reader, Ability $ability): array
    {
        return $this->perimetersApplicableTo(AccessContext::of($reader, $ability));
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
     * @internal
     *
     * @throws ImpurePerimetersException When perimeters() builds perimeters of another shape than the ones it built first.
     */
    final public function verifyPerimeters(): void
    {
        if (! $this->declaredPerimeters()->matches(new PerimeterList($this->perimeters()))) {
            throw ImpurePerimetersException::for($this);
        }
    }

    /** @return list<Perimeter> */
    private function perimetersApplicableTo(AccessContext $context): array
    {
        $applicable = [];

        foreach ($this->declaredPerimeters()->all() as $perimeter) {
            if (! $perimeter->applyAllowedCallback($context)) {
                continue;
            }

            $applicable[] = $perimeter;

            if (! $perimeter->overlays()) {
                break;
            }
        }

        return $applicable;
    }

    private function declaredPerimeters(): PerimeterList
    {
        return $this->declaredPerimeters ??= new PerimeterList($this->perimeters());
    }

    public static function new(): static
    {
        return Container::getInstance()
            ->make(ControlRegistry::class)
            ->control(static::class);
    }
}
