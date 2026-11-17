<?php

namespace Lomkit\Access\Perimeters\Parents;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Search\Parents\ParentSearch;

/** @internal */
final readonly class ParentInRequestContext implements ParentDecision
{
    /** @param  Closure(AccessContext): bool  $allowed */
    public function __construct(
        private ParentLink $link,
        private Delegation $delegation,
        private ParentSearch $search,
        private Closure $allowed,
    ) {}

    #[\Override]
    public function allows(AccessContext $context): bool
    {
        $parent = $this->parentOf($context);

        return $parent !== null
            && ($this->allowed)($context)
            && ! $this->link->control()->reached($parent)->isEmpty();
    }

    /** @param  Builder<covariant Model>  $query */
    #[\Override]
    public function scope(Builder $query, AccessContext $context): void
    {
        $parent = $this->parentOf($context);

        if ($parent === null) {
            $query->whereRaw('0=1');

            return;
        }

        $this->link->constrain($query, $parent);
    }

    #[\Override]
    public function admitsDraft(Model $draft, AccessContext $context): bool
    {
        $parent = $this->parentOf($context);

        return $parent !== null && $this->link->admitsDraft($draft, $parent);
    }

    #[\Override]
    public function fingerprint(AccessContext $context): string
    {
        $parent = $this->parentOf($context);

        if ($parent === null) {
            return '';
        }

        return $parent->ability()->name().':'.$this->link->control()->reached($parent)->fingerprint($parent);
    }

    /** @return list<string> */
    #[\Override]
    public function readerKeys(AccessContext $context): array
    {
        return $this->search->readerKeys($context);
    }

    private function parentOf(AccessContext $context): ?AccessContext
    {
        $ability = $this->delegation->parentAbilityFor($context->ability());

        return $ability === null ? null : $context->withAbility($ability);
    }
}
