<?php

namespace Lomkit\Access\Perimeters\Parents;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\AccessContext;

/** @internal */
interface ParentDecision
{
    public function allows(AccessContext $context): bool;

    /** @param  Builder<covariant Model>  $query */
    public function scope(Builder $query, AccessContext $context): void;

    public function admitsDraft(Model $draft, AccessContext $context): bool;

    public function fingerprint(AccessContext $context): string;

    /** @return list<string> */
    public function readerKeys(AccessContext $context): array;
}
