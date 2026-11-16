<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidDelegationException;
use Lomkit\Access\Exceptions\InvalidParentRelationException;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Exceptions\ParentCycleException;
use Lomkit\Access\Exceptions\ThroughParentCallbackException;
use Lomkit\Access\Perimeters\Parents\Delegation;
use Lomkit\Access\Perimeters\Parents\ParentLink;
use Lomkit\Access\Perimeters\Parents\ParentStep;
use Lomkit\Access\Search\Parents\ParentSearch;

final class ThroughParent extends Perimeter
{
    private string $relation = '';

    private ?string $inverse = null;

    private ?Delegation $delegation = null;

    private ?ParentLink $link = null;

    public static function belongsTo(string $relation, ?string $inverse = null): self
    {
        $perimeter = new self;
        $perimeter->relation = $relation;
        $perimeter->inverse = $inverse;

        return $perimeter;
    }

    /**
     * @param  array<array-key, mixed>  $abilities
     *
     * @throws InvalidDelegationException When the list is empty, names an ability twice, or holds something else than an ability.
     * @throws InvalidAbilityException When an ability name is not valid.
     */
    public function delegates(array $abilities): self
    {
        $perimeter = clone $this;
        $perimeter->delegation = Delegation::of($this->relation, $abilities);

        return $perimeter;
    }

    #[\Override]
    public static function new(): never
    {
        throw ThroughParentCallbackException::construct();
    }

    #[\Override]
    public function allowed(Closure $callback): never
    {
        throw ThroughParentCallbackException::for('allowed');
    }

    #[\Override]
    public function should(Closure $callback): never
    {
        throw ThroughParentCallbackException::for('should');
    }

    #[\Override]
    public function query(Closure $callback): never
    {
        throw ThroughParentCallbackException::for('query');
    }

    #[\Override]
    public function searchKeys(string $name, Closure $row, Closure $reader, array $load = []): never
    {
        throw ThroughParentCallbackException::for('searchKeys');
    }

    #[\Override]
    public function reindexWhen(string $model, array $changed, Closure $rows): never
    {
        throw ThroughParentCallbackException::for('reindexWhen');
    }

    #[\Override]
    public function unrestricted(): never
    {
        throw ThroughParentCallbackException::for('unrestricted');
    }

    /**
     * @internal
     *
     * @throws InvalidParentRelationException When the relation is not a belongsTo of the child model, or the inverse is not its hasMany.
     * @throws MissingParentControlException When no control is registered for the parent model.
     * @throws ParentCycleException When the parents of the parent lead back to the child.
     */
    public function boundTo(Control $child): self
    {
        $perimeter = clone $this;
        $perimeter->link = ParentLink::resolve($child, $this->relation, $this->inverse);

        return $perimeter;
    }

    /** @internal */
    public function admitsDraft(Model $draft, AccessContext $context): bool
    {
        $parent = $this->parentContext($context);

        return $parent !== null && $this->link()->admitsDraft($draft, $parent);
    }

    /** @internal */
    public function fingerprint(AccessContext $context): string
    {
        $parent = $this->parentContext($context);

        if ($parent === null) {
            return '';
        }

        return $parent->ability()->name().':'.$this->link()->control()->reached($parent)->fingerprint($parent);
    }

    /** @internal */
    public function stepFor(Ability $ability): ?ParentStep
    {
        $parent = $this->delegation()->parentAbilityFor($ability);

        return $parent === null ? null : $this->link()->stepFor($parent);
    }

    /** @internal */
    public function parentSearch(): ParentSearch
    {
        return new ParentSearch($this->relation, $this->inverse, $this->link()->control(), $this->delegation()->parentAbilityFor(Ability::view()));
    }

    /** @internal */
    public function sameDeclarationAs(self $other): bool
    {
        return $this->relation === $other->relation
            && $this->inverse === $other->inverse
            && $this->delegation()->matches($other->delegation());
    }

    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        $parent = $this->parentContext($context);

        return $parent !== null && ! $this->link()->control()->reached($parent)->isEmpty();
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $parent = $this->parentContext($context);

        if ($parent === null) {
            $query->whereRaw('0=1');

            return;
        }

        $this->link()->constrain($query, $parent);
    }

    private function parentContext(AccessContext $context): ?AccessContext
    {
        $ability = $this->delegation()->parentAbilityFor($context->ability());

        return $ability === null ? null : $context->withAbility($ability);
    }

    /** @internal */
    public function delegation(): Delegation
    {
        return $this->delegation ?? Delegation::viewOnly($this->relation);
    }

    private function link(): ParentLink
    {
        return $this->link ?? throw ThroughParentCallbackException::unbound($this->relation);
    }
}
