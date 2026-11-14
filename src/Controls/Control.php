<?php

namespace Lomkit\Access\Controls;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\ImpurePerimetersException;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\PerimeterList;
use Lomkit\Access\Registry\ControlRegistry;

class Control
{
    use ControlsQueries;
    use ControlsScoutQueries;
    use DecidesRows;

    /** @var class-string<Model> */
    protected string $model;

    private ?ControlDeclaration $declaration = null;

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

    /** @return array<Perimeter|Levels> */
    protected function perimeters(): array
    {
        return [];
    }

    protected function boundary(): Boundary
    {
        return Boundary::none();
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

    /** @return list<Perimeter> */
    final protected function applicablePerimeters(Model $reader, Ability $ability): array
    {
        return $this->declaration()->reachedBy(AccessContext::of($reader, $ability))->all();
    }

    /**
     * @internal
     *
     * @throws ImpurePerimetersException When perimeters() or boundary() builds another declaration than the one it built first.
     */
    final public function verifyPerimeters(): void
    {
        if (! $this->declaration()->matches($this->declare())) {
            throw ImpurePerimetersException::for($this);
        }
    }

    /** @internal */
    final public function declaration(): ControlDeclaration
    {
        return $this->declaration ??= $this->declare();
    }

    private function declare(): ControlDeclaration
    {
        return new ControlDeclaration(new PerimeterList($this->perimeters()), $this->boundary());
    }

    public static function new(): static
    {
        return Container::getInstance()
            ->make(ControlRegistry::class)
            ->control(static::class);
    }
}
