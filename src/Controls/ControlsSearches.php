<?php

namespace Lomkit\Access\Controls;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Ability;
use Lomkit\Access\AccessContext;
use Lomkit\Access\Search\SearchFilter;
use Lomkit\Access\Search\SearchRules;

/**
 * @internal
 *
 * @phpstan-require-extends Control
 */
trait ControlsSearches
{
    /** @internal */
    final public function searchRules(): SearchRules
    {
        return $this->declaration()->search();
    }

    /** @internal */
    final public function searchFilter(?Model $reader): SearchFilter
    {
        if ($reader === null) {
            return SearchFilter::closed();
        }

        return $this->searchFilterIn(AccessContext::of($reader, Ability::view()));
    }

    /** @internal */
    final public function searchFilterIn(AccessContext $context): SearchFilter
    {
        $perimeters = $this->reached($context);

        if ($perimeters->isEmpty()) {
            return SearchFilter::closed();
        }

        return $this->searchRules()->filterFor($perimeters, $context);
    }
}
