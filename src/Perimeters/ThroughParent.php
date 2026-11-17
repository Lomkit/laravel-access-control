<?php

namespace Lomkit\Access\Perimeters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Decisions\RowContextSwitch;
use Lomkit\Access\Exceptions\DuplicatePerimeterCallbackException;
use Lomkit\Access\Exceptions\InvalidAbilityException;
use Lomkit\Access\Exceptions\InvalidDelegationException;
use Lomkit\Access\Exceptions\InvalidParentRelationException;
use Lomkit\Access\Exceptions\InvalidRowContextColumnException;
use Lomkit\Access\Exceptions\MissingParentControlException;
use Lomkit\Access\Exceptions\MissingRowContextException;
use Lomkit\Access\Exceptions\ParentCycleException;
use Lomkit\Access\Exceptions\ThroughParentCallbackException;
use Lomkit\Access\Perimeters\Parents\Delegation;
use Lomkit\Access\Perimeters\Parents\ParentDecision;
use Lomkit\Access\Perimeters\Parents\ParentInRequestContext;
use Lomkit\Access\Perimeters\Parents\ParentInRowContext;
use Lomkit\Access\Perimeters\Parents\ParentLink;
use Lomkit\Access\Perimeters\Parents\ParentStep;
use Lomkit\Access\Perimeters\Parents\RowContextColumn;
use Lomkit\Access\Search\Parents\ParentSearch;

final class ThroughParent extends Perimeter
{
    private string $relation = '';

    private ?string $inverse = null;

    private ?Delegation $delegation = null;

    private ?RowContextColumn $rowContext = null;

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

    /**
     * @throws InvalidRowContextColumnException When the column is not the plain name of a column.
     * @throws DuplicatePerimeterCallbackException When the perimeter already decides its parent in the row context.
     */
    public function decidedInRowContext(string $column): self
    {
        if ($this->rowContext !== null) {
            throw DuplicatePerimeterCallbackException::rowContext($this->relation, $this->rowContext->name());
        }

        $perimeter = clone $this;
        $perimeter->rowContext = RowContextColumn::of($this->relation, $column);

        return $perimeter;
    }

    #[\Override]
    public static function new(): never
    {
        throw ThroughParentCallbackException::construct();
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
     * @throws MissingRowContextException When the perimeter decides its parent in the row context and the application declares none.
     * @throws InvalidParentRelationException When the relation is not a belongsTo of the child model, or the inverse is not its hasMany.
     * @throws MissingParentControlException When no control is registered for the parent model.
     * @throws ParentCycleException When the parents of the parent lead back to the child.
     */
    public function boundTo(Control $child): self
    {
        if ($this->rowContext !== null && ! RowContextSwitch::current()->isDeclared()) {
            throw MissingRowContextException::for($child, $this->relation, $this->rowContext->name());
        }

        $perimeter = clone $this;
        $perimeter->link = ParentLink::resolve($child, $this->relation, $this->inverse);

        return $perimeter;
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
        return new ParentSearch($this->relation, $this->inverse, $this->link()->control(), $this->delegation()->parentAbilityFor(Ability::view()), $this->rowContext?->name());
    }

    /** @internal */
    public function sameDeclarationAs(self $other): bool
    {
        return $this->relation === $other->relation
            && $this->inverse === $other->inverse
            && $this->rowContext?->name() === $other->rowContext?->name()
            && $this->delegation()->matches($other->delegation());
    }

    /** @internal */
    public function rowContextColumn(): ?string
    {
        return $this->rowContext?->name();
    }

    /** @internal */
    public function delegation(): Delegation
    {
        return $this->delegation ?? Delegation::viewOnly($this->relation);
    }

    #[\Override]
    protected function allows(AccessContext $context): bool
    {
        return $this->decision()->allows($context);
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    protected function scope(Builder $query, AccessContext $context): void
    {
        $this->decision()->scope($query, $context);
    }

    /** @internal */
    public function decision(): ParentDecision
    {
        $search = $this->parentSearch();
        $request = new ParentInRequestContext($this->link(), $this->delegation(), $search, fn (AccessContext $context): bool => ! $this->declaresAllowed() || parent::allows($context));

        return $this->rowContext === null ? $request : new ParentInRowContext($request, $this->delegation(), $search, $this->rowContext->name(), $this);
    }

    private function link(): ParentLink
    {
        return $this->link ?? throw ThroughParentCallbackException::unbound($this->relation);
    }
}
