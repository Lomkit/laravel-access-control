<?php

namespace Lomkit\Access\Perimeters\Parents;

use Lomkit\Access\Ability;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidDelegationException;

/** @internal */
final readonly class Delegation
{
    /** @param  non-empty-array<string, Ability>  $parents */
    private function __construct(
        private string $relation,
        private array $parents,
    ) {}

    public static function viewOnly(string $relation): self
    {
        return new self($relation, ['view' => Ability::view()]);
    }

    /**
     * @param  array<array-key, mixed>  $abilities
     *
     * @throws InvalidDelegationException When the list is empty, names an ability twice, or holds something else than an ability.
     * @throws InvalidAbilityException When an ability name is not valid.
     */
    public static function of(string $relation, array $abilities): self
    {
        $parents = [];

        foreach ($abilities as $child => $parent) {
            $parentAbility = match (true) {
                $parent instanceof Ability => $parent,
                is_string($parent) => Ability::of($parent),
                default => throw InvalidDelegationException::notAnAbility($relation, $child, $parent),
            };
            $childName = is_int($child) ? $parentAbility->name() : Ability::of($child)->name();

            if (array_key_exists($childName, $parents)) {
                throw InvalidDelegationException::duplicate($relation, $childName);
            }

            $parents[$childName] = $parentAbility;
        }

        if ($parents === []) {
            throw InvalidDelegationException::empty($relation);
        }

        return new self($relation, $parents);
    }

    public function relation(): string
    {
        return $this->relation;
    }

    public function parentAbilityFor(Ability $child): ?Ability
    {
        return $this->parents[$child->name()] ?? null;
    }

    public function matches(self $other): bool
    {
        return $this->names() === $other->names();
    }

    /** @return array<string, string> */
    public function names(): array
    {
        $names = array_map(fn (Ability $ability): string => $ability->name(), $this->parents);

        ksort($names);

        return $names;
    }
}
